<?php

/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace MpSoft\MpMassImportProducts\Helpers;

use Context;
use Module;

class ProductImportManager
{
    private Module $module;
    private Context $context;
    private array $featureIdCache = [];
    private array $featureValueIdCache = [];
    private array $attributeGroupIdCache = [];
    private array $attributeIdCache = [];

    public function __construct(Module $module, ?Context $context = null)
    {
        $this->module = $module;
        $this->context = $context ?: Context::getContext();
    }

    private function resetProductFieldsForMapping(\Product $product, array $mapping, int $idLang): void
    {
        $fields = [];
        foreach ($mapping as $header => $field) {
            if (!is_string($field)) {
                continue;
            }
            $field = trim($field);
            if ($field === '' || str_starts_with($field, '__')) {
                continue;
            }
            $fields[$field] = true;
        }

        foreach (array_keys($fields) as $field) {
            if (!property_exists($product, $field)) {
                continue;
            }

            if (isset($product->{$field}) && is_array($product->{$field}) && isset($product->{$field}[$idLang])) {
                $product->{$field}[$idLang] = '';
                continue;
            }

            $product->{$field} = '';
        }
    }

    public function loadTemplate(string $templateName): array
    {
        $templatesRaw = (string) \Configuration::get('MPMASSIMPORTPRODUCTS_MAPPING_TEMPLATES');
        $templates = json_decode($templatesRaw, true);
        if (!is_array($templates) || !isset($templates[$templateName]) || !is_array($templates[$templateName])) {
            throw new \InvalidArgumentException('Invalid template');
        }

        $tpl = $templates[$templateName];
        return [
            'name' => (string) ($tpl['name'] ?? $templateName),
            'mapping' => is_array($tpl['mapping'] ?? null) ? $tpl['mapping'] : [],
            'validation_rules' => is_array($tpl['validation_rules'] ?? null) ? $tpl['validation_rules'] : [],
            'attribute_alias' => is_array($tpl['attribute_alias'] ?? null) ? $tpl['attribute_alias'] : [],
            'feature_alias' => is_array($tpl['feature_alias'] ?? null) ? $tpl['feature_alias'] : [],
        ];
    }

    public function importChunk(array $params): array
    {
        $filePath = (string) ($params['filePath'] ?? ($params['file_path'] ?? ''));
        $type = strtolower((string) ($params['type'] ?? ''));
        $template = (array) ($params['template'] ?? []);
        $offset = (int) ($params['offset'] ?? 0);
        $limit = (int) ($params['limit'] ?? 50);

        $csvSettings = is_array($params['csv_settings'] ?? null) ? $params['csv_settings'] : [];
        $csvDelimiter = (string) ($csvSettings['delimiter'] ?? ',');
        $csvEnclosure = (string) ($csvSettings['enclosure'] ?? '"');
        $csvEscape = (string) ($csvSettings['escape'] ?? '\\');

        $csvDelimiter = $csvDelimiter !== '' ? $csvDelimiter[0] : ',';
        $csvEnclosure = $csvEnclosure !== '' ? $csvEnclosure[0] : '"';
        $csvEscape = $csvEscape !== '' ? $csvEscape[0] : '\\';

        if ($limit <= 0) {
            $limit = 50;
        }
        if ($limit > 500) {
            $limit = 500;
        }

        if ($filePath === '' || !is_file($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException('File not readable');
        }
        if (!in_array($type, ['csv', 'xlsx'], true)) {
            throw new \InvalidArgumentException('Unsupported type');
        }

        $defaults = is_array($params['defaults'] ?? null) ? $params['defaults'] : [];
        $priceSettings = is_array($params['price_settings'] ?? null) ? $params['price_settings'] : [];

        if (!isset($priceSettings['markup_rules'])) {
            $markupRulesRaw = (string) \Configuration::get('MPMASSIMPORTPRODUCTS_PRICE_MARKUP_RULES');
            $markupRules = json_decode($markupRulesRaw, true);
            if (!is_array($markupRules)) {
                $markupRules = [];
            }
            $priceSettings['markup_rules'] = $markupRules;
        }

        $duplicateBehavior = strtolower((string) ($defaults['duplicate_behavior'] ?? 'overwrite'));
        if (!in_array($duplicateBehavior, ['overwrite', 'skip'], true)) {
            $duplicateBehavior = 'overwrite';
        }

        $resetProducts = $params['resetProducts'] ?? [];
        if (!is_array($resetProducts)) {
            $resetProducts = [];
        }
        $resetProducts = array_fill_keys(array_map('intval', $resetProducts), true);

        $idLang = (int) ($defaults['id_lang'] ?? $this->context->language->id);
        if ($idLang <= 0) {
            $idLang = (int) $this->context->language->id;
        }

        $headers = $this->readHeaders($filePath, $type, $csvDelimiter, $csvEnclosure, $csvEscape);
        if (!$headers) {
            return [
                'done' => true,
                'nextOffset' => $offset,
                'stats' => [
                    'processed' => 0,
                    'imported' => 0,
                    'skipped' => 0,
                    'errors' => 1,
                ],
                'errors' => ['No headers'],
            ];
        }

        $mapping = is_array($template['mapping'] ?? null) ? $template['mapping'] : [];
        $validationRules = is_array($template['validation_rules'] ?? null) ? $template['validation_rules'] : [];
        $attributeAlias = is_array($template['attribute_alias'] ?? null) ? $template['attribute_alias'] : [];
        $featureAlias = is_array($template['feature_alias'] ?? null) ? $template['feature_alias'] : [];

        $rows = $this->readRows($filePath, $type, $offset, $limit, $csvDelimiter, $csvEnclosure, $csvEscape);

        $stats = [
            'processed' => 0,
            'imported' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
        $errors = [];
        $warnings = [];

        $seenReferences = [];

        foreach ($rows as $i => $row) {
            $stats['processed']++;
            $rowIndex = $offset + $i + 1;

            $dataByHeader = [];
            foreach ($headers as $idx => $h) {
                if (!is_string($h) || $h === '') {
                    continue;
                }
                $dataByHeader[$h] = $row[$idx] ?? null;
            }

            $rowReference = $this->getCombinedMappedValue($mapping, $dataByHeader, 'reference');
            $isDuplicateReference = ($rowReference !== '' && isset($seenReferences[$rowReference]));
            $existingProductId = 0;
            if ($rowReference !== '') {
                $existingProductId = (int) \Product::getIdByReference($rowReference);
            }

            if ($existingProductId > 0 && $duplicateBehavior === 'skip') {
                $stats['skipped']++;
                $stats['errors']++;
                if (count($errors) < 50) {
                    $errors[] = 'Row ' . $rowIndex . ': Reference already exists, skipped: ' . $rowReference;
                }
                continue;
            }
            if ($isDuplicateReference && $duplicateBehavior === 'skip') {
                $stats['skipped']++;
                $stats['errors']++;
                if (count($errors) < 50) {
                    $errors[] = 'Row ' . $rowIndex . ': Duplicate reference skipped: ' . $rowReference;
                }
                continue;
            }

            $overwriteRow = ($duplicateBehavior === 'overwrite') && ($existingProductId > 0 || $isDuplicateReference);
            if ($overwriteRow && $rowReference !== '' && count($warnings) < 200) {
                if ($existingProductId > 0) {
                    $warnings[] = 'Row ' . $rowIndex . ': Reference duplicato (già esistente): ' . $rowReference . ' → sovrascrivo prodotto ID ' . $existingProductId;
                } else {
                    $warnings[] = 'Row ' . $rowIndex . ': Reference duplicato nel file: ' . $rowReference . ' → sovrascrivo';
                }
            }

            $check = $this->validateRow($headers, $row, $validationRules);
            if ($check !== true) {
                $stats['skipped']++;
                if (count($errors) < 50) {
                    $errors[] = 'Row ' . $rowIndex . ': ' . $check;
                }
                continue;
            }

            try {
                $result = $this->importRow(
                    $headers,
                    $row,
                    $mapping,
                    $attributeAlias,
                    $featureAlias,
                    $defaults,
                    $priceSettings,
                    $idLang,
                    $resetProducts,
                    $overwriteRow,
                    $overwriteRow
                );
                if ($result) {
                    $stats['imported']++;
                    if ($rowReference !== '') {
                        $seenReferences[$rowReference] = true;
                    }
                } else {
                    $stats['skipped']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $stats['skipped']++;
                if (count($errors) < 50) {
                    $errors[] = 'Row ' . $rowIndex . ': ' . $e->getMessage();
                }
            }
        }

        $nextOffset = $offset + count($rows);
        $done = count($rows) < $limit;

        return [
            'done' => $done,
            'nextOffset' => $nextOffset,
            'stats' => $stats,
            'errors' => $errors,
            'warnings' => $warnings,
            'resetProducts' => array_map('intval', array_keys($resetProducts)),
        ];
    }

    private function importRow(array $headers, array $row, array $mapping, array $attributeAlias, array $featureAlias, array $defaults, array $priceSettings, int $idLang, array &$resetProducts, bool $forceReset, bool $overwriteRow): bool
    {
        $dataByHeader = [];
        foreach ($headers as $idx => $h) {
            if (!is_string($h) || $h === '') {
                continue;
            }
            $dataByHeader[$h] = $row[$idx] ?? null;
        }

        $usePriceAsWholesale = (bool) ($defaults['use_price_as_wholesale'] ?? false);
        $rawPrice = $this->getCombinedMappedValue($mapping, $dataByHeader, 'price');
        $basePrice = $this->parseNumber($rawPrice);
        if ($basePrice !== null) {
            $dataByHeader['__mpmassimport_original_price__'] = (float) $basePrice;
        }

        $reference = $this->getCombinedMappedValue($mapping, $dataByHeader, 'reference');

        $forceIdProduct = (bool) ($defaults['force_id_product'] ?? false);
        $rawIdProduct = $this->getCombinedMappedValue($mapping, $dataByHeader, 'id_product');
        if ($rawIdProduct === '' && isset($dataByHeader['id_product'])) {
            $rawIdProduct = is_scalar($dataByHeader['id_product']) ? (string) $dataByHeader['id_product'] : '';
        }
        $idProductFromFile = (int) $this->parseNumber($rawIdProduct);

        $idProduct = 0;
        if ($forceIdProduct && $idProductFromFile > 0) {
            $idProduct = $idProductFromFile;
        } elseif ($reference !== '') {
            $idProduct = (int) \Product::getIdByReference($reference);
        }

        $product = new \Product($idProduct ?: null, false, $idLang);
        if ($forceIdProduct && $idProductFromFile > 0 && !$product->id) {
            $product->id = $idProductFromFile;
            $product->force_id = true;
        }
        $isNew = !$product->id;

        if ($overwriteRow && $product->id) {
            $this->resetProductFieldsForMapping($product, $mapping, $idLang);
        }

        if ($product->id && ($forceReset || !isset($resetProducts[(int) $product->id]))) {
            $product->deleteImages();
            $product->deleteFeatures();
            $product->deleteProductAttributes();
            $resetProducts[(int) $product->id] = true;
        }

        $product->id_supplier = (int) ($defaults['id_supplier'] ?? 0);
        $product->id_manufacturer = (int) ($defaults['id_manufacturer'] ?? 0);
        $product->id_tax_rules_group = (int) ($priceSettings['id_tax_rules_group'] ?? 0);

        $idCategoryDefault = (int) ($defaults['id_category_default'] ?? 0);
        if ($idCategoryDefault > 0) {
            $product->id_category_default = $idCategoryDefault;
        }

        $stockQuantity = null;
        $categoryIds = [];
        $images = [];
        $featurePairs = [];
        $attributePairs = [];
        $mappingHasAttributes = in_array('__ATTRIBUTE__', $mapping, true);

        foreach ($mapping as $header => $field) {
            if (!is_string($header) || $header === '' || !is_string($field)) {
                continue;
            }

            $field = trim($field);
            if ($field === '') {
                continue;
            }

            $value = $dataByHeader[$header] ?? null;

            if ($field === '__CATEGORY__') {
                $categoryIds = $this->resolveCategories($value, $idLang);
                continue;
            }
            if ($field === '__STOCK__') {
                $stockQuantity = $this->parseNumber($value);
                continue;
            }
            if ($field === '__IMAGES__') {
                $images = array_merge($images, $this->parseImageList($value));
                continue;
            }
            if ($field === '__FEATURE__') {
                $name = (string) ($featureAlias[$header] ?? '');
                if ($name === '') {
                    $name = $header;
                }
                $vals = $this->splitMultiValues($value);
                foreach ($vals as $v) {
                    $v = trim($v);
                    if ($v !== '') {
                        $featurePairs[] = ['name' => $name, 'value' => $v];
                    }
                }
                continue;
            }
            if ($field === '__ATTRIBUTE__') {
                $group = (string) ($attributeAlias[$header] ?? '');
                if ($group === '') {
                    $group = $header;
                }
                $vals = $this->splitMultiValues($value);
                foreach ($vals as $v) {
                    $v = trim($v);
                    if ($v !== '') {
                        $attributePairs[] = ['group' => $group, 'value' => $v];
                    }
                }
                continue;
            }

            if (property_exists($product, $field)) {
                $this->mergeProductField($product, $field, $value, $idLang);
            }
        }

        if ($usePriceAsWholesale && isset($dataByHeader['__mpmassimport_original_price__'])) {
            $product->wholesale_price = (float) $dataByHeader['__mpmassimport_original_price__'];
        }

        if ($basePrice !== null) {
            $rules = $priceSettings['markup_rules'] ?? [];
            if (is_array($rules) && $rules) {
                $taxRulesGroupId = (int) ($priceSettings['id_tax_rules_group'] ?? 0);
                $taxRate = $this->getTaxRateForGroup($taxRulesGroupId);
                $mk = $this->computeMarkup((float) $basePrice, $rules, $taxRate);
                if (isset($mk['final_excl']) && $mk['final_excl'] !== null) {
                    $product->price = (float) $mk['final_excl'];
                } else {
                    $product->price = (float) $basePrice + (float) ($mk['amount'] ?? 0.0);
                }
            } else {
                $product->price = (float) $basePrice;
            }
        }

        if (empty($product->name) || (is_array($product->name) && empty($product->name[$idLang]))) {
            $name = $this->getCombinedMappedValue($mapping, $dataByHeader, 'name');
            if ($name !== '') {
                $product->name = [$idLang => $name];
            }
        }

        if (!isset($product->name[$idLang]) || trim((string) $product->name[$idLang]) === '') {
            return false;
        }

        if (is_array($product->name)) {
            foreach ($product->name as $idLangName => $productName) {
                $product->link_rewrite[$idLangName] = \Tools::str2url($productName);
            }
        } else {
            $name = [$idLang => $product->name];
            $link = [$idLang => \Tools::str2url($product->name)];
            $product->name = $name;
            $product->link_rewrite = $link;
        }

        if ($product->id_category_default <= 0 && $categoryIds) {
            $product->id_category_default = (int) $categoryIds[0];
        }

        if ($product->id_category_default <= 0) {
            $product->id_category_default = (int) \Configuration::get('PS_HOME_CATEGORY');
        }

        $categoriesToAdd = [];
        if ($product->id_category_default > 0) {
            $categoriesToAdd[] = (int) $product->id_category_default;
        }
        foreach ($categoryIds as $cid) {
            $cid = (int) $cid;
            if ($cid > 0) {
                $categoriesToAdd[] = $cid;
            }
        }
        $categoriesToAdd = array_values(array_unique($categoriesToAdd));

        $ok = $product->id ? (bool) $product->update() : (bool) $product->add();
        if (!$ok) {
            return false;
        }

        $idSupplier = (int) ($defaults['id_supplier'] ?? 0);
        if ($idSupplier > 0 && (int) $product->id > 0) {
            $product->addSupplierReference($idSupplier, 0);
        }

        if ($categoriesToAdd) {
            $product->addToCategories($categoriesToAdd);
        }

        if ($stockQuantity !== null && !$mappingHasAttributes) {
            \StockAvailable::setQuantity((int) $product->id, 0, (int) round($stockQuantity), null, false);
        }

        if ($featurePairs) {
            $this->applyFeatures((int) $product->id, $featurePairs, $idLang);
        }

        $idProductAttribute = 0;
        if ($attributePairs) {
            $idProductAttribute = $this->applyAttributesAndGetCombinationId((int) $product->id, $attributePairs, $idLang);
        }

        if ($images) {
            $this->applyImages((int) $product->id, $images);
        }

        return true;
    }

    private function applyFeatures(int $idProduct, array $pairs, int $idLang): void
    {
        foreach ($pairs as $p) {
            if (!is_array($p)) {
                continue;
            }
            $name = trim((string) ($p['name'] ?? ''));
            $value = trim((string) ($p['value'] ?? ''));
            if ($name === '' || $value === '') {
                continue;
            }

            $idFeature = $this->getOrCreateFeatureId($name, $idLang);
            if ($idFeature <= 0) {
                continue;
            }
            $idFeatureValue = $this->getOrCreateFeatureValueId($idFeature, $value, $idLang);
            if ($idFeatureValue <= 0) {
                continue;
            }
            \Product::addFeatureProductImport($idProduct, $idFeature, $idFeatureValue);
        }
    }

    private function applyAttributesAndGetCombinationId(int $idProduct, array $pairs, int $idLang): int
    {
        $attributeIds = [];
        foreach ($pairs as $p) {
            if (!is_array($p)) {
                continue;
            }
            $group = trim((string) ($p['group'] ?? ''));
            $value = trim((string) ($p['value'] ?? ''));
            if ($group === '' || $value === '') {
                continue;
            }

            $idGroup = $this->getOrCreateAttributeGroupId($group, $idLang);
            if ($idGroup <= 0) {
                continue;
            }

            $idAttr = $this->getOrCreateAttributeId($idGroup, $value, $idLang);
            if ($idAttr <= 0) {
                continue;
            }

            $attributeIds[$idGroup] = $idAttr;
        }

        if (!$attributeIds) {
            return 0;
        }

        $idAttributes = array_values($attributeIds);
        sort($idAttributes);

        $hasCombinations = (int) \Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'product_attribute WHERE id_product = ' . (int) $idProduct) > 0;
        $product = new \Product($idProduct);
        $comb = new \Combination();
        $comb->id_product = $idProduct;
        $comb->quantity = 0;
        $comb->default_on = $hasCombinations ? 0 : 1;
        $comb->minimal_quantity = 1;
        $comb->reference = $product->reference;
        $comb->add();

        foreach ($idAttributes as $idAttr) {
            \Db::getInstance()->insert('product_attribute_combination', [
                'id_product_attribute' => (int) $comb->id,
                'id_attribute' => (int) $idAttr,
            ]);
        }

        return (int) $comb->id;
    }

    private function applyImages(int $idProduct, array $images): void
    {
        $images = array_values(array_unique(array_filter($images, function ($v) {
            return is_string($v) && trim($v) !== '';
        })));

        if (!$images) {
            return;
        }

        $first = true;
        foreach ($images as $src) {
            $src = trim($src);
            if ($src === '') {
                continue;
            }

            $image = new \Image();
            $image->id_product = $idProduct;
            $image->position = \Image::getHighestPosition($idProduct) + 1;
            $image->cover = $first ? 1 : 0;
            $image->add();

            $ok = false;
            if (preg_match('#^https?://#i', $src)) {
                $ok = \ImageManager::copyImg($idProduct, (int) $image->id, $src, 'products', true);
            } else {
                $local = $src;
                if (!str_starts_with($local, '/')) {
                    $local = _PS_ROOT_DIR_ . '/' . ltrim($local, '/');
                }
                if (is_file($local)) {
                    $tmp = tempnam(sys_get_temp_dir(), 'mpimg_');
                    if ($tmp) {
                        copy($local, $tmp);
                        $ok = \ImageManager::copyImg($idProduct, (int) $image->id, $tmp, 'products', true);
                        @unlink($tmp);
                    }
                }
            }

            if (!$ok) {
                $image->delete();
            } else {
                $first = false;
            }
        }
    }

    private function getOrCreateFeatureId(string $name, int $idLang): int
    {
        $k = strtolower(trim($name));
        if ($k === '') {
            return 0;
        }
        if (isset($this->featureIdCache[$k])) {
            return (int) $this->featureIdCache[$k];
        }

        $id = (int) \Db::getInstance()->getValue(
            'SELECT id_feature FROM ' . _DB_PREFIX_ . 'feature_lang WHERE id_lang = ' . (int) $idLang . ' AND name = "' . pSQL($name) . '"'
        );
        if ($id <= 0) {
            $f = new \Feature();
            $f->name = [$idLang => $name];
            $f->add();
            $id = (int) $f->id;
        }

        $this->featureIdCache[$k] = $id;
        return $id;
    }

    private function getOrCreateFeatureValueId(int $idFeature, string $value, int $idLang): int
    {
        $k = $idFeature . '|' . strtolower(trim($value));
        if ($k === $idFeature . '|') {
            return 0;
        }
        if (isset($this->featureValueIdCache[$k])) {
            return (int) $this->featureValueIdCache[$k];
        }

        $id = (int) \Db::getInstance()->getValue(
            'SELECT fv.id_feature_value
             FROM ' . _DB_PREFIX_ . 'feature_value fv
             INNER JOIN ' . _DB_PREFIX_ . 'feature_value_lang fvl ON fvl.id_feature_value = fv.id_feature_value AND fvl.id_lang = ' . (int) $idLang . '
             WHERE fv.id_feature = ' . (int) $idFeature . ' AND fvl.value = "' . pSQL($value) . '"'
        );
        if ($id <= 0) {
            $fv = new \FeatureValue();
            $fv->id_feature = $idFeature;
            $fv->custom = 0;
            $fv->value = [$idLang => $value];
            $fv->add();
            $id = (int) $fv->id;
        }

        $this->featureValueIdCache[$k] = $id;
        return $id;
    }

    private function getOrCreateAttributeGroupId(string $name, int $idLang): int
    {
        $k = strtolower(trim($name));
        if ($k === '') {
            return 0;
        }
        if (isset($this->attributeGroupIdCache[$k])) {
            return (int) $this->attributeGroupIdCache[$k];
        }

        $id = (int) \Db::getInstance()->getValue(
            'SELECT id_attribute_group FROM ' . _DB_PREFIX_ . 'attribute_group_lang WHERE id_lang = ' . (int) $idLang . ' AND name = "' . pSQL($name) . '"'
        );
        if ($id <= 0) {
            $g = new \AttributeGroup();
            $g->is_color_group = 0;
            $g->group_type = 'select';
            $g->name = [$idLang => $name];
            $g->public_name = [$idLang => $name];
            $g->add();
            $id = (int) $g->id;
        }

        $this->attributeGroupIdCache[$k] = $id;
        return $id;
    }

    private function getOrCreateAttributeId(int $idGroup, string $value, int $idLang): int
    {
        $k = $idGroup . '|' . strtolower(trim($value));
        if ($k === $idGroup . '|') {
            return 0;
        }
        if (isset($this->attributeIdCache[$k])) {
            return (int) $this->attributeIdCache[$k];
        }

        $id = (int) \Db::getInstance()->getValue(
            'SELECT a.id_attribute
             FROM ' . _DB_PREFIX_ . 'attribute a
             INNER JOIN ' . _DB_PREFIX_ . 'attribute_lang al ON al.id_attribute = a.id_attribute AND al.id_lang = ' . (int) $idLang . '
             WHERE a.id_attribute_group = ' . (int) $idGroup . ' AND al.name = "' . pSQL($value) . '"'
        );
        if ($id <= 0) {
            $a = new \ProductAttribute();
            $a->id_attribute_group = (int) $idGroup;
            $a->name = [$idLang => $value];
            $a->add();
            $id = (int) $a->id;
        }

        $this->attributeIdCache[$k] = $id;
        return $id;
    }

    private function parseImageList($value): array
    {
        $raw = is_scalar($value) ? (string) $value : '';
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[\r\n,;|]+/', $raw);
        if (!is_array($parts)) {
            $parts = [];
        }
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }

    private function splitMultiValues($value): array
    {
        $raw = is_scalar($value) ? (string) $value : '';
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[\r\n,;|]+/', $raw);
        if (!is_array($parts)) {
            $parts = [];
        }
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }

    private function assignProductField(\Product $product, string $field, $value, int $idLang): void
    {
        $raw = is_scalar($value) ? (string) $value : '';

        if (in_array($field, ['price', 'wholesale_price', 'ecotax', 'weight', 'width', 'height', 'depth', 'additional_shipping_cost', 'unit_price', 'unit_price_ratio'], true)) {
            $num = $this->parseNumber($value);
            if ($num !== null) {
                $product->{$field} = (float) $num;
            }
            return;
        }

        if (in_array($field, ['quantity', 'minimal_quantity', 'low_stock_threshold', 'out_of_stock', 'active', 'indexed', 'available_for_order', 'show_price'], true)) {
            $product->{$field} = (int) $raw;
            return;
        }

        if ($field != 'link_rewrite') {
            $product->{$field} = $raw;
        }
    }

    private function mergeProductField(\Product $product, string $field, $value, int $idLang): void
    {
        $raw = is_scalar($value) ? (string) $value : '';
        $raw = trim($raw);
        if ($raw === '') {
            return;
        }

        if (strtolower($field) === 'ean13') {
            $digits = preg_replace('/\D+/', '', $raw);
            $digits = is_string($digits) ? $digits : '';
            $digits = substr($digits, 0, 13);
            $digits = trim($digits);
            if ($digits === '') {
                return;
            }
            $product->{$field} = $digits;
            return;
        }

        if (is_array($product->$field)) {
            $existing = '';
            if (isset($product->{$field}[$idLang]) && is_scalar($product->{$field}[$idLang])) {
                $existing = (string) $product->{$field}[$idLang];
            }
            $combined = $this->combineFieldValues($field, $existing, $raw);
            if ($field === 'link_rewrite') {
                $combined = \Tools::str2url($combined);
            }
            $product->{$field} = [$idLang => $combined];
            return;
        }

        if (in_array($field, ['price', 'wholesale_price', 'ecotax', 'weight', 'width', 'height', 'depth', 'additional_shipping_cost', 'unit_price', 'unit_price_ratio'], true)) {
            $num = $this->parseNumber($value);
            if ($num !== null) {
                $product->{$field} = (float) $num;
            }
            return;
        }

        if (in_array($field, ['quantity', 'minimal_quantity', 'low_stock_threshold', 'out_of_stock', 'active', 'indexed', 'available_for_order', 'show_price'], true)) {
            $product->{$field} = (int) $raw;
            return;
        }

        $existing = is_scalar($product->$field) ? (string) $product->$field : '';
        $product->{$field} = $this->combineFieldValues($field, $existing, $raw);
    }

    private function combineFieldValues(string $field, string $existing, string $incoming): string
    {
        $existing = trim($existing);
        $incoming = trim($incoming);

        if ($incoming === '') {
            return $existing;
        }
        if ($existing === '') {
            return $incoming;
        }

        $field = strtolower($field);
        if ($field === 'reference') {
            return $existing . '-' . $incoming;
        }
        if (in_array($field, ['ean13', 'isbn', 'upc', 'mpn', 'supplier_reference'], true)) {
            return $existing . $incoming;
        }

        if (in_array($field, ['description', 'description_short', 'meta_description'], true)) {
            return $existing . "\n" . $incoming;
        }

        return $existing . ' ' . $incoming;
    }

    private function computeMarkup(float $basePrice, array $rules, float $taxRate = 0.0): array
    {
        $matched = null;
        foreach ($rules as $r) {
            if (!is_array($r) || !isset($r['min'])) {
                continue;
            }
            $min = is_numeric($r['min']) ? (float) $r['min'] : null;
            $max = isset($r['max']) && $r['max'] !== null && $r['max'] !== '' && is_numeric($r['max']) ? (float) $r['max'] : null;
            if ($min === null) {
                continue;
            }
            if ($basePrice < $min) {
                continue;
            }
            if ($max !== null && $basePrice > $max) {
                continue;
            }
            $matched = $r;
            break;
        }

        if (!$matched) {
            return [
                'amount' => 0.0,
                'label' => '',
                'final_excl' => null,
                'final_incl' => null,
            ];
        }

        $type = (string) ($matched['type'] ?? 'percent');
        $vatMode = strtolower((string) ($matched['vat_mode'] ?? 'no'));
        $value = isset($matched['value']) && is_numeric($matched['value']) ? (float) $matched['value'] : 0.0;
        $roundStep = isset($matched['round_step']) && is_numeric($matched['round_step']) ? (float) $matched['round_step'] : 0.0;
        $roundMode = strtolower((string) ($matched['round_mode'] ?? 'ceil'));

        if (!in_array($type, ['percent', 'fixed'], true)) {
            $type = 'percent';
        }
        if (!in_array($vatMode, ['no', 'si', 'scorpora'], true)) {
            $vatMode = 'no';
        }
        if ($roundStep < 0) {
            $roundStep = 0.0;
        }
        if (!in_array($roundMode, ['ceil', 'round', 'floor'], true)) {
            $roundMode = 'ceil';
        }

        $taxFactor = 1 + ($taxRate / 100);

        $working = $basePrice;
        if ($vatMode === 'si') {
            $working = $basePrice * $taxFactor;
        } elseif ($vatMode === 'scorpora') {
            $working = $taxRate > 0 ? ($basePrice / $taxFactor) : $basePrice;
        }

        $after = $working;
        if ($type === 'fixed') {
            $after = $working + $value;
        } else {
            $after = $working * (1 + ($value / 100));
        }

        $afterRounded = $after;
        if ($roundStep > 0) {
            $factor = $after / $roundStep;
            if ($roundMode === 'floor') {
                $afterRounded = floor($factor) * $roundStep;
            } elseif ($roundMode === 'round') {
                $afterRounded = round($factor) * $roundStep;
            } else {
                $afterRounded = ceil($factor) * $roundStep;
            }
        }

        $finalExcl = $afterRounded;
        if ($vatMode === 'si') {
            $finalExcl = $taxRate > 0 ? ($afterRounded / $taxFactor) : $afterRounded;
        } elseif ($vatMode === 'scorpora') {
            $finalExcl = $afterRounded;
        }
        $finalExcl = round($finalExcl, 6);
        $finalIncl = $finalExcl * $taxFactor;

        $amount = $finalExcl - $basePrice;

        $label = '';
        if ($type === 'fixed') {
            $label = sprintf('+%.2f', $value);
        } else {
            $label = sprintf('+%s%%', rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.'));
        }

        return [
            'amount' => $amount,
            'label' => $label,
            'final_excl' => $finalExcl,
            'final_incl' => $finalIncl,
        ];
    }

    private function getTaxRateForGroup(int $taxRulesGroupId): float
    {
        if ($taxRulesGroupId <= 0) {
            return 0.0;
        }

        $sql = 'SELECT SUM(DISTINCT t.rate)
            FROM ' . _DB_PREFIX_ . 'tax_rule tr
            INNER JOIN ' . _DB_PREFIX_ . 'tax t ON (t.id_tax = tr.id_tax AND t.active = 1)
            WHERE tr.id_tax_rules_group = ' . (int) $taxRulesGroupId;

        $v = \Db::getInstance()->getValue($sql);
        if ($v === false || $v === null || $v === '') {
            return 0.0;
        }

        return (float) $v;
    }

    private function getCombinedMappedValue(array $mapping, array $dataByHeader, string $field): string
    {
        $parts = [];
        foreach ($mapping as $header => $mapped) {
            if ((string) $mapped !== $field) {
                continue;
            }
            if (!is_string($header) || $header === '') {
                continue;
            }
            $val = $dataByHeader[$header] ?? '';
            $val = is_scalar($val) ? trim((string) $val) : '';
            if ($val !== '') {
                $parts[] = $val;
            }
        }

        if (!$parts) {
            return '';
        }

        if (strtolower($field) === 'reference') {
            return implode('-', $parts);
        }

        if (in_array(strtolower($field), ['ean13', 'isbn', 'upc', 'mpn', 'supplier_reference'], true)) {
            return implode('', $parts);
        }

        if (in_array(strtolower($field), ['description', 'description_short', 'meta_description'], true)) {
            return implode("\n", $parts);
        }

        return trim(implode(' ', $parts));
    }

    private function validateRow(array $headers, array $row, array $rulesByHeader)
    {
        if (!$rulesByHeader) {
            return true;
        }

        $indexes = [];
        foreach ($headers as $idx => $h) {
            if (is_string($h) && $h !== '') {
                $indexes[$h] = (int) $idx;
            }
        }

        foreach ($rulesByHeader as $header => $rule) {
            if (!is_string($header) || $header === '' || !isset($indexes[$header]) || !is_array($rule)) {
                continue;
            }
            $idx = $indexes[$header];
            $value = $row[$idx] ?? null;
            $check = $this->validateValueAgainstRule($value, $rule);
            if ($check !== true) {
                return $header . ': ' . $check;
            }
        }

        return true;
    }

    private function validateValueAgainstRule($value, array $rule)
    {
        $type = strtolower(trim((string) ($rule['type'] ?? 'none')));
        if ($type === '' || $type === 'none') {
            return true;
        }

        $raw = is_scalar($value) ? (string) $value : '';
        $rawTrim = trim($raw);

        if ($type === 'regex') {
            $patternRaw = (string) ($rule['value'] ?? '');
            $pattern = $this->normalizeRegexPattern($patternRaw);
            if ($pattern === '') {
                return 'Invalid regex';
            }
            $ok = @preg_match($pattern, $rawTrim);
            if ($ok !== 1) {
                return 'Regex not matched';
            }
            return true;
        }

        if ($type === 'in_list') {
            $listRaw = (string) ($rule['value'] ?? '');
            $parts = preg_split('/[\r\n,;|]+/', $listRaw);
            if (!is_array($parts)) {
                $parts = [];
            }
            $allowed = [];
            foreach ($parts as $p) {
                $p = trim((string) $p);
                if ($p !== '') {
                    $allowed[$p] = true;
                }
            }
            if (!$allowed) {
                return 'Empty list';
            }
            if (!isset($allowed[$rawTrim])) {
                return 'Value not allowed';
            }
            return true;
        }

        $num = $this->parseNumber($value);
        if ($num === null) {
            return 'Not a number';
        }

        if ($type === 'gte') {
            $min = $this->parseNumber($rule['value'] ?? null);
            if ($min === null) {
                return 'Invalid min value';
            }
            return $num >= $min ? true : 'Value is lower than minimum';
        }

        if ($type === 'lte') {
            $max = $this->parseNumber($rule['value'] ?? null);
            if ($max === null) {
                return 'Invalid max value';
            }
            return $num <= $max ? true : 'Value is greater than maximum';
        }

        if ($type === 'range') {
            $min = $this->parseNumber($rule['min'] ?? null);
            $max = $this->parseNumber($rule['max'] ?? null);
            if ($min === null || $max === null || $min > $max) {
                return 'Invalid range';
            }
            return ($num >= $min && $num <= $max) ? true : 'Value out of range';
        }

        return true;
    }

    private function normalizeRegexPattern(string $patternRaw): string
    {
        $patternRaw = trim($patternRaw);
        if ($patternRaw === '') {
            return '';
        }
        if (preg_match('#^/.+/[a-z]*$#i', $patternRaw)) {
            return $patternRaw;
        }
        $escaped = str_replace('#', '\#', $patternRaw);
        return '#' . $escaped . '#';
    }

    private function parseNumber($value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (!is_string($value)) {
            return null;
        }
        $v = trim($value);
        if ($v === '') {
            return null;
        }

        $v = str_replace([' ', "\t"], '', $v);
        $v = str_replace(['€', '%'], '', $v);
        if (substr_count($v, ',') > 0 && substr_count($v, '.') === 0) {
            $v = str_replace(',', '.', $v);
        } elseif (substr_count($v, ',') > 0 && substr_count($v, '.') > 0) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        }

        if (!is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }

    private function resolveCategories($value, int $idLang): array
    {
        $raw = is_scalar($value) ? (string) $value : '';
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\r\n,;|]+/', $raw);
        if (!is_array($parts)) {
            $parts = [];
        }

        $ids = [];
        foreach ($parts as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $found = \Category::searchByName($idLang, $name, true, true);
            if (is_array($found) && isset($found['id_category'])) {
                $ids[] = (int) $found['id_category'];
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));
        return $ids;
    }

    private function findHeaderMappedTo(array $mapping, string $field): ?string
    {
        foreach ($mapping as $header => $mapped) {
            if ((string) $mapped === $field) {
                return is_string($header) ? $header : null;
            }
        }
        return null;
    }

    private function readHeaders(string $filePath, string $type, string $csvDelimiter, string $csvEnclosure, string $csvEscape): array
    {
        if ($type === 'csv') {
            $fh = fopen($filePath, 'rb');
            if (!$fh) {
                return [];
            }
            $line = fgetcsv($fh, 0, $csvDelimiter, $csvEnclosure, $csvEscape);
            fclose($fh);
            if (!is_array($line)) {
                return [];
            }
            return array_map(function ($v) {
                return is_string($v) ? trim($v) : '';
            }, $line);
        }

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestColumn = $sheet->getHighestColumn();
        $data = $sheet->rangeToArray('A1:' . $highestColumn . '1', null, true, true, false);
        $spreadsheet->disconnectWorksheets();

        $row = $data[0] ?? [];
        if (!is_array($row)) {
            return [];
        }
        return array_map(function ($v) {
            return is_string($v) ? trim($v) : (is_scalar($v) ? trim((string) $v) : '');
        }, $row);
    }

    private function readRows(string $filePath, string $type, int $offset, int $limit, string $csvDelimiter, string $csvEnclosure, string $csvEscape): array
    {
        if ($type === 'csv') {
            $rows = [];
            $file = new \SplFileObject($filePath, 'rb');
            $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
            $file->setCsvControl($csvDelimiter, $csvEnclosure, $csvEscape);

            $start = $offset + 1;
            $file->seek($start);
            $count = 0;
            while (!$file->eof() && $count < $limit) {
                $r = $file->current();
                $file->next();
                if (!is_array($r) || (count($r) === 1 && ($r[0] === null || $r[0] === ''))) {
                    continue;
                }
                $rows[] = $r;
                $count++;
            }
            return $rows;
        }

        $startRow = $offset + 2;
        $endRow = $startRow + $limit - 1;

        $filter = new class($startRow, $endRow) implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
            private int $start;
            private int $end;

            public function __construct(int $start, int $end)
            {
                $this->start = $start;
                $this->end = $end;
            }

            public function readCell($column, $row, $worksheetName = ''): bool
            {
                return $row === 1 || ($row >= $this->start && $row <= $this->end);
            }
        };

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $reader->setReadFilter($filter);
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $highestDataRow = (int) $sheet->getHighestDataRow();
        if ($highestDataRow < $startRow) {
            $spreadsheet->disconnectWorksheets();
            return [];
        }
        if ($endRow > $highestDataRow) {
            $endRow = $highestDataRow;
        }

        $highestColumn = $sheet->getHighestDataColumn();
        $data = $sheet->rangeToArray('A' . $startRow . ':' . $highestColumn . $endRow, null, true, true, false);
        $spreadsheet->disconnectWorksheets();

        $rows = [];
        foreach ($data as $r) {
            if (is_array($r)) {
                $rows[] = $r;
            }
        }
        return $rows;
    }
}
