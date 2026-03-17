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
class AdminMpMassImportProductsController extends ModuleAdminController
{
    private const LARGE_FILE_BYTES = 10485760;
    private const PREVIEW_ROWS = 50;
    private const PRICE_SETTINGS_TAX_RULES_GROUP = 'MPMASSIMPORTPRODUCTS_TAX_RULES_GROUP_ID';
    private const PRICE_SETTINGS_MARKUP_RULES = 'MPMASSIMPORTPRODUCTS_PRICE_MARKUP_RULES';
    private const DEFAULT_CATEGORY_ID = 'MPMASSIMPORTPRODUCTS_DEFAULT_CATEGORY_ID';
    private const DEFAULT_SUPPLIER_ID = 'MPMASSIMPORTPRODUCTS_DEFAULT_SUPPLIER_ID';
    private const DEFAULT_MANUFACTURER_ID = 'MPMASSIMPORTPRODUCTS_DEFAULT_MANUFACTURER_ID';
    private const VALIDATION_RULES = 'MPMASSIMPORTPRODUCTS_VALIDATION_RULES';
    private const LAST_URL = 'MPMASSIMPORTPRODUCTS_LAST_URL';
    private const LAST_ZIP = 'MPMASSIMPORTPRODUCTS_LAST_ZIP';
    private const DEFAULT_LANG_ID = 'MPMASSIMPORTPRODUCTS_DEFAULT_LANG_ID';
    private const CSV_SETTINGS = 'MPMASSIMPORTPRODUCTS_CSV_SETTINGS';
    private const DUPLICATE_BEHAVIOR = 'MPMASSIMPORTPRODUCTS_DUPLICATE_BEHAVIOR';
    private const USE_PRICE_AS_WHOLESALE = 'MPMASSIMPORTPRODUCTS_USE_PRICE_AS_WHOLESALE';
    private const FORCE_ID_PRODUCT = 'MPMASSIMPORTPRODUCTS_FORCE_ID_PRODUCT';

    public function __construct()
    {
        $this->module = Module::getInstanceByName('mpmassimportproducts');
        $this->translator = Context::getContext()->getTranslator();

        $this->bootstrap = true;
        $this->context = Context::getContext();
        $this->table = 'product';
        $this->className = 'Product';
        $this->identifier = 'id_product';
        $this->lang = true;

        parent::__construct();
    }

    private function ensureDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return is_dir($dir) && is_writable($dir);
    }

    public function _initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();
        unset($this->page_header_toolbar_btn['new']);
        $this->page_header_toolbar_btn = [
            'configure' => [
                'href' => $this->context->link->getAdminLink($this->controller_name) . '&action=configure',
                'desc' => $this->trans('Configuration'),
            ],
        ];
    }

    public function _initToolbar()
    {
        parent::initToolbar();
        unset($this->toolbar_btn['new']);
        $this->toolbar_btn = [
            'configure' => [
                'href' => $this->context->link->getAdminLink($this->controller_name) . '&action=configure',
                'desc' => $this->trans('Configuration'),
            ],
        ];
    }

    public function initContent()
    {
        $endpoint = $this->context->link->getAdminLink($this->controller_name);
        $lastURL = Configuration::get(self::LAST_URL);

        $lastZipRaw = (string) \Configuration::get(self::LAST_ZIP);
        $lastZip = json_decode($lastZipRaw, true);
        if (!is_array($lastZip)) {
            $lastZip = [];
        }

        $templates = $this->getMappingTemplates();
        $activeTemplateName = (string) \Configuration::get('MPMASSIMPORTPRODUCTS_ACTIVE_TEMPLATE');
        if ($activeTemplateName !== '' && !isset($templates[$activeTemplateName])) {
            $activeTemplateName = '';
        }

        $headers = [];
        $uploadedName = '';
        $uploadedPath = '';
        $uploadedType = '';
        $fileSizeBytes = 0;
        $isLargeFile = false;
        $analyzedPartial = false;

        $csvSettings = [
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\',
        ];
        $csvSettingsRaw = (string) \Configuration::get(self::CSV_SETTINGS);
        $csvSettingsSaved = json_decode($csvSettingsRaw, true);
        if (is_array($csvSettingsSaved)) {
            if (isset($csvSettingsSaved['delimiter']) && is_string($csvSettingsSaved['delimiter']) && $csvSettingsSaved['delimiter'] !== '') {
                $csvSettings['delimiter'] = $csvSettingsSaved['delimiter'][0];
            }
            if (isset($csvSettingsSaved['enclosure']) && is_string($csvSettingsSaved['enclosure']) && $csvSettingsSaved['enclosure'] !== '') {
                $csvSettings['enclosure'] = $csvSettingsSaved['enclosure'][0];
            }
            if (isset($csvSettingsSaved['escape']) && is_string($csvSettingsSaved['escape']) && $csvSettingsSaved['escape'] !== '') {
                $csvSettings['escape'] = $csvSettingsSaved['escape'][0];
            }
        }

        $lastFileRaw = (string) \Configuration::get('MPMASSIMPORTPRODUCTS_LAST_XLSX');
        $lastFile = json_decode($lastFileRaw, true);
        if (!is_array($lastFile)) {
            $lastFile = [];
        }
        if (isset($lastFile['name']) && is_string($lastFile['name'])) {
            $uploadedName = $lastFile['name'];
        }
        if (isset($lastFile['path']) && is_string($lastFile['path'])) {
            $uploadedPath = $lastFile['path'];
        }
        if (isset($lastFile['type']) && is_string($lastFile['type'])) {
            $uploadedType = $lastFile['type'];
        }

        if ($activeTemplateName !== '' && isset($templates[$activeTemplateName]) && is_array($templates[$activeTemplateName])) {
            $tpl = $templates[$activeTemplateName];
            if (isset($tpl['file']) && is_array($tpl['file'])) {
                $tplFile = $tpl['file'];
                if (isset($tplFile['name']) && is_string($tplFile['name'])) {
                    $uploadedName = $tplFile['name'];
                }
                if (isset($tplFile['path']) && is_string($tplFile['path'])) {
                    $uploadedPath = $tplFile['path'];
                }
                if (isset($tplFile['type']) && is_string($tplFile['type'])) {
                    $uploadedType = $tplFile['type'];
                }
            }
        }

        if ($uploadedPath && is_file($uploadedPath)) {
            $fileSizeBytes = (int) @filesize($uploadedPath);
            $isLargeFile = $fileSizeBytes >= self::LARGE_FILE_BYTES;
        }

        if (Tools::isSubmit('submitMpMassImportUploadXlsx')) {
            $upload = $this->handleFileUpload('xlsx_file', ['xlsx']);
            if ($upload) {
                $uploadedName = $upload['name'];
                $uploadedPath = $upload['path'];
                $uploadedType = $upload['type'];

                $fileSizeBytes = (int) @filesize($uploadedPath);
                $isLargeFile = $fileSizeBytes >= self::LARGE_FILE_BYTES;

                \Configuration::updateValue('MPMASSIMPORTPRODUCTS_LAST_XLSX', json_encode([
                    'name' => $uploadedName,
                    'path' => $uploadedPath,
                    'type' => $uploadedType,
                ]));

                try {
                    $headers = $this->readFileHeaders($uploadedPath, $uploadedType);
                    if (!$headers) {
                        $this->errors[] = $this->trans('No headers found in the first row of the file.');
                    }
                } catch (\Throwable $e) {
                    $this->errors[] = $this->trans('Error while reading XLSX:') . ' ' . $e->getMessage();
                }
            }
        }

        if (Tools::isSubmit('submitMpMassImportPriceSettings')) {
            $taxRulesGroupId = (int) Tools::getValue('tax_rules_group_id');
            $markupRules = Tools::getValue('markup_rules');
            if (!is_array($markupRules)) {
                $markupRules = [];
            }

            $normalizedRules = [];
            foreach ($markupRules as $r) {
                if (!is_array($r)) {
                    continue;
                }
                $min = $this->parseNumber($r['min'] ?? null);
                $max = $this->parseNumber($r['max'] ?? null);
                $rawValue = $r['value'] ?? null;
                $rawValueStr = is_scalar($rawValue) ? trim((string) $rawValue) : '';
                $type = (string) ($r['type'] ?? 'percent');
                $vatMode = strtolower((string) ($r['vat_mode'] ?? 'no'));
                $value = $this->parseNumber($rawValue);
                $roundStep = $this->parseNumber($r['round_step'] ?? null);
                $roundMode = strtolower((string) ($r['round_mode'] ?? 'ceil'));

                if ($min === null || $value === null) {
                    continue;
                }
                // Derive type from value syntax: "7%" => percent, otherwise fixed amount.
                if ($rawValueStr !== '' && substr($rawValueStr, -1) === '%') {
                    $type = 'percent';
                } else {
                    $type = 'fixed';
                }
                if (!in_array($vatMode, ['no', 'si', 'scorpora'], true)) {
                    $vatMode = 'no';
                }
                if ($roundStep === null || $roundStep < 0) {
                    $roundStep = 0.0;
                }
                if (!in_array($roundMode, ['ceil', 'round', 'floor'], true)) {
                    $roundMode = 'ceil';
                }
                if ($max !== null && $max < $min) {
                    continue;
                }

                $normalizedRules[] = [
                    'min' => $min,
                    'max' => $max,
                    'type' => $type,
                    'vat_mode' => $vatMode,
                    'value' => $value,
                    'round_step' => $roundStep,
                    'round_mode' => $roundMode,
                ];
            }

            usort($normalizedRules, function ($a, $b) {
                return $a['min'] <=> $b['min'];
            });

            \Configuration::updateValue(self::PRICE_SETTINGS_TAX_RULES_GROUP, $taxRulesGroupId);
            \Configuration::updateValue(self::PRICE_SETTINGS_MARKUP_RULES, json_encode($normalizedRules));
            $this->confirmations[] = $this->trans('Price settings saved.');
        }

        if (Tools::isSubmit('submitMpMassImportDefaultParameters')) {
            $defaultCategoryId = (int) Tools::getValue('default_category_id');
            $defaultManufacturerId = (int) Tools::getValue('id_manufacturer');
            $defaultSupplierId = (int) Tools::getValue('id_supplier');
            $defaultLangId = (int) Tools::getValue('id_lang');
            $usePriceAsWholesale = (bool) Tools::getValue('use_price_as_wholesale', false);
            $forceIdProduct = (bool) Tools::getValue('force_id_product', false);
            $duplicateBehavior = strtolower((string) Tools::getValue('duplicate_behavior', 'overwrite'));
            if (!in_array($duplicateBehavior, ['overwrite', 'skip'], true)) {
                $duplicateBehavior = 'overwrite';
            }

            \Configuration::updateValue(self::DEFAULT_CATEGORY_ID, $defaultCategoryId);
            \Configuration::updateValue(self::DEFAULT_MANUFACTURER_ID, $defaultManufacturerId);
            \Configuration::updateValue(self::DEFAULT_SUPPLIER_ID, $defaultSupplierId);
            \Configuration::updateValue(self::DEFAULT_LANG_ID, $defaultLangId);
            \Configuration::updateValue(self::DUPLICATE_BEHAVIOR, $duplicateBehavior);
            \Configuration::updateValue(self::USE_PRICE_AS_WHOLESALE, $usePriceAsWholesale ? 1 : 0);
            \Configuration::updateValue(self::FORCE_ID_PRODUCT, $forceIdProduct ? 1 : 0);

            $this->confirmations[] = $this->trans('Default parameters saved.');
        }

        if (Tools::isSubmit('submitMpMassImportCsvSettings')) {
            $posted = Tools::getValue('csv_settings');
            if (!is_array($posted)) {
                $posted = [];
            }
            $d = isset($posted['delimiter']) && is_string($posted['delimiter']) && $posted['delimiter'] !== '' ? $posted['delimiter'][0] : ',';
            $e = isset($posted['enclosure']) && is_string($posted['enclosure']) && $posted['enclosure'] !== '' ? $posted['enclosure'][0] : '"';
            $esc = isset($posted['escape']) && is_string($posted['escape']) && $posted['escape'] !== '' ? $posted['escape'][0] : '\\';
            $csvSettings = [
                'delimiter' => $d,
                'enclosure' => $e,
                'escape' => $esc,
            ];
            \Configuration::updateValue(self::CSV_SETTINGS, json_encode($csvSettings));
            $this->confirmations[] = $this->trans('CSV settings saved.');
        }

        if (Tools::isSubmit('submitMpMassImportUploadCsv')) {
            $postedCsvSettings = Tools::getValue('csv_settings');
            if (is_array($postedCsvSettings)) {
                $d = isset($postedCsvSettings['delimiter']) && is_string($postedCsvSettings['delimiter']) && $postedCsvSettings['delimiter'] !== '' ? $postedCsvSettings['delimiter'][0] : $csvSettings['delimiter'];
                $e = isset($postedCsvSettings['enclosure']) && is_string($postedCsvSettings['enclosure']) && $postedCsvSettings['enclosure'] !== '' ? $postedCsvSettings['enclosure'][0] : $csvSettings['enclosure'];
                $esc = isset($postedCsvSettings['escape']) && is_string($postedCsvSettings['escape']) && $postedCsvSettings['escape'] !== '' ? $postedCsvSettings['escape'][0] : $csvSettings['escape'];
                $csvSettings = [
                    'delimiter' => $d,
                    'enclosure' => $e,
                    'escape' => $esc,
                ];
                \Configuration::updateValue(self::CSV_SETTINGS, json_encode($csvSettings));
            }
            $upload = $this->handleFileUpload('csv_file', ['csv']);
            if ($upload) {
                $uploadedName = $upload['name'];
                $uploadedPath = $upload['path'];
                $uploadedType = $upload['type'];

                $fileSizeBytes = (int) @filesize($uploadedPath);
                $isLargeFile = $fileSizeBytes >= self::LARGE_FILE_BYTES;

                \Configuration::updateValue('MPMASSIMPORTPRODUCTS_LAST_XLSX', json_encode([
                    'name' => $uploadedName,
                    'path' => $uploadedPath,
                    'type' => $uploadedType,
                ]));

                try {
                    $headers = $this->readFileHeaders($uploadedPath, $uploadedType, $csvSettings);
                    if (!$headers) {
                        $this->errors[] = $this->trans('No headers found in the first row of the file.');
                    }
                } catch (\Throwable $e) {
                    $this->errors[] = $this->trans('Error while reading CSV:') . ' ' . $e->getMessage();
                }
            }
        }

        if (Tools::isSubmit('submitMpMassImportUploadUrl')) {
            $url = trim((string) Tools::getValue('source_url'));
            $zipEntry = trim((string) Tools::getValue('zip_entry', ''));
            $downloadDir = rtrim(_PS_DOWNLOAD_DIR_, '/\\') . '/mpmassimport';
            if ($url === '') {
                $this->errors[] = $this->trans('URL is required.');
            } else {
                \Configuration::updateValue(self::LAST_URL, $url);
                try {
                    $download = $this->downloadFromUrl($url);

                    if (($download['type'] ?? '') === 'zip') {
                        $zipPath = (string) ($download['path'] ?? '');
                        $zipName = (string) ($download['name'] ?? '');
                        $entries = $this->listZipSupportedEntries($zipPath);

                        $selectedEntry = $zipEntry;
                        if ($selectedEntry === '' && isset($lastZip['selected_entry']) && is_string($lastZip['selected_entry'])) {
                            $selectedEntry = (string) $lastZip['selected_entry'];
                        }
                        if ($selectedEntry === '' && $entries) {
                            $selectedEntry = (string) $entries[0];
                        }

                        \Configuration::updateValue(self::LAST_ZIP, json_encode([
                            'url' => $url,
                            'name' => $zipName,
                            'path' => $zipPath,
                            'entries' => $entries,
                            'selected_entry' => $selectedEntry,
                        ]));
                        $lastZip = [
                            'url' => $url,
                            'name' => $zipName,
                            'path' => $zipPath,
                            'entries' => $entries,
                            'selected_entry' => $selectedEntry,
                        ];

                        // If the user selected an entry, extract it and continue as usual.
                        if ($selectedEntry !== '' && in_array($selectedEntry, $entries, true)) {
                            $extracted = $this->extractZipEntryToDownloadDir($zipPath, $downloadDir, $selectedEntry);
                            $uploadedName = $extracted['name'];
                            $uploadedPath = $extracted['path'];
                            $uploadedType = $extracted['type'];
                        } else {
                            // Stop here: show the ZIP contents to let the user choose.
                            $uploadedName = '';
                            $uploadedPath = '';
                            $uploadedType = '';
                        }
                    } else {
                        $uploadedName = $download['name'];
                        $uploadedPath = $download['path'];
                        $uploadedType = $download['type'];
                    }

                    if ($uploadedPath) {
                        $fileSizeBytes = (int) @filesize($uploadedPath);
                        $isLargeFile = $fileSizeBytes >= self::LARGE_FILE_BYTES;
                    }

                    if ($uploadedPath) {
                        \Configuration::updateValue('MPMASSIMPORTPRODUCTS_LAST_XLSX', json_encode([
                            'name' => $uploadedName,
                            'path' => $uploadedPath,
                            'type' => $uploadedType,
                        ]));
                    }

                    if ($uploadedPath) {
                        $headers = $this->readFileHeaders($uploadedPath, $uploadedType, $csvSettings);
                        if (!$headers) {
                            $this->errors[] = $this->trans('No headers found in the first row of the file.');
                        }
                    }
                } catch (\Throwable $e) {
                    $this->errors[] = $this->trans('Error while downloading:') . ' ' . $e->getMessage();
                }
            }
        }

        if (Tools::isSubmit('submitMpMassImportMapping')) {
            $mapping = Tools::getValue('header_map');
            if (!is_array($mapping)) {
                $mapping = [];
            }

            $validationRules = Tools::getValue('validation_rules');
            if (!is_array($validationRules)) {
                $validationRules = [];
            }
            $attributeAlias = Tools::getValue('attribute_alias');
            if (!is_array($attributeAlias)) {
                $attributeAlias = [];
            }
            $featureAlias = Tools::getValue('feature_alias');
            if (!is_array($featureAlias)) {
                $featureAlias = [];
            }
            \Configuration::updateValue('MPMASSIMPORTPRODUCTS_HEADER_MAP', json_encode($mapping));
            \Configuration::updateValue(self::VALIDATION_RULES, json_encode($validationRules));
            \Configuration::updateValue('MPMASSIMPORTPRODUCTS_ATTRIBUTE_ALIAS', json_encode($attributeAlias));
            \Configuration::updateValue('MPMASSIMPORTPRODUCTS_FEATURE_ALIAS', json_encode($featureAlias));
            $this->confirmations[] = $this->trans('Mapping saved.');
        }

        if (Tools::isSubmit('submitMpMassImportTemplateLoad')) {
            $templateName = (string) Tools::getValue('template_name');
            if ($templateName === '' || !isset($templates[$templateName])) {
                $this->errors[] = $this->trans('Please select a valid template.');
            } else {
                $tplMapping = $templates[$templateName]['mapping'] ?? [];
                if (!is_array($tplMapping)) {
                    $tplMapping = [];
                }
                $tplAttributeAlias = $templates[$templateName]['attribute_alias'] ?? [];
                if (!is_array($tplAttributeAlias)) {
                    $tplAttributeAlias = [];
                }
                $tplFeatureAlias = $templates[$templateName]['feature_alias'] ?? [];
                if (!is_array($tplFeatureAlias)) {
                    $tplFeatureAlias = [];
                }

                $tplValidationRules = $templates[$templateName]['validation_rules'] ?? [];
                if (!is_array($tplValidationRules)) {
                    $tplValidationRules = [];
                }
                \Configuration::updateValue('MPMASSIMPORTPRODUCTS_HEADER_MAP', json_encode($tplMapping));
                \Configuration::updateValue(self::VALIDATION_RULES, json_encode($tplValidationRules));
                \Configuration::updateValue('MPMASSIMPORTPRODUCTS_ATTRIBUTE_ALIAS', json_encode($tplAttributeAlias));
                \Configuration::updateValue('MPMASSIMPORTPRODUCTS_FEATURE_ALIAS', json_encode($tplFeatureAlias));
                \Configuration::updateValue('MPMASSIMPORTPRODUCTS_ACTIVE_TEMPLATE', $templateName);
                $activeTemplateName = $templateName;
                $this->confirmations[] = $this->trans('Template loaded.');
            }
        }

        if (Tools::isSubmit('submitMpMassImportTemplateDelete')) {
            $templateName = (string) Tools::getValue('template_name');
            if ($templateName === '' || !isset($templates[$templateName])) {
                $this->errors[] = $this->trans('Please select a valid template.');
            } else {
                unset($templates[$templateName]);
                $this->saveMappingTemplates($templates);
                if ($activeTemplateName === $templateName) {
                    \Configuration::updateValue('MPMASSIMPORTPRODUCTS_ACTIVE_TEMPLATE', '');
                    $activeTemplateName = '';
                }
                $this->confirmations[] = $this->trans('Template deleted.');
            }
        }

        if (Tools::isSubmit('submitMpMassImportTemplateSave')) {
            $templateName = trim((string) Tools::getValue('template_name'));
            $overwrite = (bool) Tools::getValue('template_overwrite');
            $mapping = Tools::getValue('header_map');
            if (!is_array($mapping)) {
                $mapping = [];
            }

            $validationRules = Tools::getValue('validation_rules');
            if (!is_array($validationRules)) {
                $validationRules = [];
            }

            $attributeAlias = Tools::getValue('attribute_alias');
            if (!is_array($attributeAlias)) {
                $attributeAlias = [];
            }
            $featureAlias = Tools::getValue('feature_alias');
            if (!is_array($featureAlias)) {
                $featureAlias = [];
            }

            if ($templateName === '') {
                $this->errors[] = $this->trans('Template name is required.');
            } elseif (isset($templates[$templateName]) && !$overwrite) {
                $this->errors[] = $this->trans('A template with this name already exists. Choose another name or overwrite it.');
            } else {
                $lastFileRaw = (string) \Configuration::get('MPMASSIMPORTPRODUCTS_LAST_XLSX');
                $lastFile = json_decode($lastFileRaw, true);
                if (!is_array($lastFile)) {
                    $lastFile = [];
                }
                $tplFile = [];
                if (isset($lastFile['name'], $lastFile['path'], $lastFile['type']) && is_string($lastFile['name']) && is_string($lastFile['path']) && is_string($lastFile['type'])) {
                    $tplFile = [
                        'name' => $lastFile['name'],
                        'path' => $lastFile['path'],
                        'type' => $lastFile['type'],
                    ];
                }

                $templates[$templateName] = [
                    'name' => $templateName,
                    'updated_at' => date('c'),
                    'mapping' => $mapping,
                    'validation_rules' => $validationRules,
                    'attribute_alias' => $attributeAlias,
                    'feature_alias' => $featureAlias,
                    'file' => $tplFile,
                ];
                $this->saveMappingTemplates($templates);
                \Configuration::updateValue('MPMASSIMPORTPRODUCTS_ACTIVE_TEMPLATE', $templateName);
                $activeTemplateName = $templateName;
                $this->confirmations[] = $this->trans('Template saved.');
            }
        }

        $savedMappingRaw = (string) \Configuration::get('MPMASSIMPORTPRODUCTS_HEADER_MAP');
        $savedMapping = json_decode($savedMappingRaw, true);
        if (!is_array($savedMapping)) {
            $savedMapping = [];
        }

        $savedValidationRaw = (string) \Configuration::get(self::VALIDATION_RULES);
        $savedValidationRules = json_decode($savedValidationRaw, true);
        if (!is_array($savedValidationRules)) {
            $savedValidationRules = [];
        }

        $savedAttributeAliasRaw = (string) \Configuration::get('MPMASSIMPORTPRODUCTS_ATTRIBUTE_ALIAS');
        $savedAttributeAlias = json_decode($savedAttributeAliasRaw, true);
        if (!is_array($savedAttributeAlias)) {
            $savedAttributeAlias = [];
        }

        $savedFeatureAliasRaw = (string) \Configuration::get('MPMASSIMPORTPRODUCTS_FEATURE_ALIAS');
        $savedFeatureAlias = json_decode($savedFeatureAliasRaw, true);
        if (!is_array($savedFeatureAlias)) {
            $savedFeatureAlias = [];
        }

        $selectedTaxRulesGroupId = (int) \Configuration::get(self::PRICE_SETTINGS_TAX_RULES_GROUP);
        $markupRulesRaw = (string) \Configuration::get(self::PRICE_SETTINGS_MARKUP_RULES);
        $markupRules = json_decode($markupRulesRaw, true);
        if (!is_array($markupRules)) {
            $markupRules = [];
        }
        $taxRuleGroupOptions = $this->getTaxRulesGroupOptions();
        $taxRate = $this->getTaxRateForGroup($selectedTaxRulesGroupId);

        $selectedDefaultCategoryId = (int) \Configuration::get(self::DEFAULT_CATEGORY_ID);
        $categoryOptions = $this->getCategoryOptions();

        $selectedDefaultManufacturerId = (int) \Configuration::get(self::DEFAULT_MANUFACTURER_ID);
        $manufacturerOptions = $this->getManufacturerOptions();

        $selectedDefaultSupplierId = (int) \Configuration::get(self::DEFAULT_SUPPLIER_ID);
        $supplierOptions = $this->getSupplierOptions();

        $selectedDefaultLangId = (int) \Configuration::get(self::DEFAULT_LANG_ID);
        if ($selectedDefaultLangId <= 0) {
            $selectedDefaultLangId = (int) $this->context->language->id;
        }
        $languageOptions = $this->getLanguageOptions();

        $selectedDuplicateBehavior = strtolower((string) \Configuration::get(self::DUPLICATE_BEHAVIOR));
        if (!in_array($selectedDuplicateBehavior, ['overwrite', 'skip'], true)) {
            $selectedDuplicateBehavior = 'overwrite';
        }

        $selectedUsePriceAsWholesale = (bool) \Configuration::get(self::USE_PRICE_AS_WHOLESALE);
        $selectedForceIdProduct = (bool) \Configuration::get(self::FORCE_ID_PRODUCT);

        $templates = $this->getMappingTemplates();
        if ($activeTemplateName !== '' && !isset($templates[$activeTemplateName])) {
            $activeTemplateName = '';
        }

        if (!$headers && $uploadedPath && is_file($uploadedPath) && is_readable($uploadedPath)) {
            try {
                $headers = $this->readFileHeaders($uploadedPath, $uploadedType, $csvSettings);
            } catch (\Throwable $e) {
                $this->errors[] = $this->trans('Error while reading XLSX:') . ' ' . $e->getMessage();
            }
        }

        $autoMapping = $this->buildAutoMapping($headers);
        $headerMeta = $this->buildHeaderMeta($headers);

        $effectiveMapping = $this->buildEffectiveMapping($headers, $savedMapping, $autoMapping);

        $hasCategoriesColumn = false;
        foreach ($effectiveMapping as $m) {
            if ($m === '__CATEGORY__') {
                $hasCategoriesColumn = true;
                break;
            }
        }
        $previewRows = [];
        if ($uploadedPath && is_file($uploadedPath) && is_readable($uploadedPath)) {
            try {
                $previewRows = $this->readFilePreview($uploadedPath, $uploadedType, self::PREVIEW_ROWS, $csvSettings);
                $analyzedPartial = $isLargeFile;
            } catch (\Throwable $e) {
                $this->errors[] = $this->trans('Error while reading XLSX preview:') . ' ' . $e->getMessage();
            }
        }

        $validationReport = [
            'invalid_rows' => 0,
            'errors' => [],
        ];
        if ($headers && $previewRows) {
            $validationReport = $this->validatePreviewRows($headers, $previewRows, $savedValidationRules);
        }

        $previewPrice = [
            'enabled' => false,
            'tax_rate' => $taxRate,
            'tax_rules_group_id' => $selectedTaxRulesGroupId,
            'rows' => [],
        ];
        if ($previewRows && $headers) {
            $priceHeaderIndex = null;
            foreach ($headers as $idx => $h) {
                if (!is_string($h) || $h === '') {
                    continue;
                }
                if (isset($effectiveMapping[$h]) && $effectiveMapping[$h] === 'price') {
                    $priceHeaderIndex = (int) $idx;
                    break;
                }
            }

            if ($priceHeaderIndex !== null) {
                $previewPrice['enabled'] = true;
                foreach ($previewRows as $r) {
                    $base = $this->parseNumber($r[$priceHeaderIndex] ?? null);
                    if ($base === null) {
                        $previewPrice['rows'][] = [
                            'base' => null,
                            'markup_value' => null,
                            'markup_label' => '',
                            'final_excl' => null,
                            'final_incl' => null,
                        ];
                        continue;
                    }

                    $mk = $this->computeMarkup($base, $markupRules, $taxRate);
                    $finalExcl = isset($mk['final_excl']) && $mk['final_excl'] !== null ? (float) $mk['final_excl'] : ($base + (float) $mk['amount']);
                    $finalIncl = isset($mk['final_incl']) && $mk['final_incl'] !== null ? (float) $mk['final_incl'] : ($finalExcl * (1 + ($taxRate / 100)));

                    $previewPrice['rows'][] = [
                        'base' => $base,
                        'markup_value' => $mk['amount'],
                        'markup_label' => $mk['label'],
                        'final_excl' => $finalExcl,
                        'final_incl' => $finalIncl,
                    ];
                }
            }
        }

        $this->content = $this->module->renderTemplate('Admin/AdminTemplate', [
            'endpoint' => $endpoint,
            'token' => $this->token,
            'importManagerJs' => __PS_BASE_URI__ . 'modules/' . $this->module->name . '/views/assets/js/ImportManager.js',
            'uploadedName' => $uploadedName,
            'uploadedPath' => $uploadedPath,
            'uploadedType' => $uploadedType,
            'fileSizeBytes' => $fileSizeBytes,
            'isLargeFile' => $isLargeFile,
            'analyzedPartial' => $analyzedPartial,
            'headers' => $headers,
            'autoMapping' => $autoMapping,
            'savedMapping' => $savedMapping,
            'savedValidationRules' => $savedValidationRules,
            'savedAttributeAlias' => $savedAttributeAlias,
            'savedFeatureAlias' => $savedFeatureAlias,
            'headerMeta' => $headerMeta,
            'productFields' => $this->getProductFieldOptions(),
            'effectiveMapping' => $effectiveMapping,
            'previewRows' => $previewRows,
            'validationReport' => $validationReport,
            'taxRuleGroupOptions' => $taxRuleGroupOptions,
            'selectedTaxRulesGroupId' => $selectedTaxRulesGroupId,
            'markupRules' => $markupRules,
            'taxRate' => $taxRate,
            'previewPrice' => $previewPrice,
            'categoryOptions' => $categoryOptions,
            'selectedDefaultCategoryId' => $selectedDefaultCategoryId,
            'hasCategoriesColumn' => $hasCategoriesColumn,
            'manufacturerOptions' => $manufacturerOptions,
            'selectedDefaultManufacturerId' => $selectedDefaultManufacturerId,
            'supplierOptions' => $supplierOptions,
            'selectedDefaultSupplierId' => $selectedDefaultSupplierId,
            'languageOptions' => $languageOptions,
            'selectedDefaultLangId' => $selectedDefaultLangId,
            'selectedDuplicateBehavior' => $selectedDuplicateBehavior,
            'selectedUsePriceAsWholesale' => $selectedUsePriceAsWholesale,
            'selectedForceIdProduct' => $selectedForceIdProduct,
            'mappingTemplates' => $this->formatMappingTemplatesForSelect($templates),
            'activeTemplateName' => $activeTemplateName,
            'lastURL' => $lastURL,
            'lastZip' => $lastZip,
            'csvSettings' => $csvSettings,
        ]);

        parent::initContent();
    }

    private function handleFileUpload(string $fieldName, array $allowedExtensions)
    {
        if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
            $this->errors[] = $this->trans('No file uploaded.');
            return false;
        }

        $file = $_FILES[$fieldName];
        if (!empty($file['error'])) {
            $this->errors[] = $this->trans('Upload error.');
            return false;
        }

        $originalName = (string) ($file['name'] ?? '');
        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($originalName === '' || $tmpName === '' || !is_uploaded_file($tmpName)) {
            $this->errors[] = $this->trans('Invalid upload.');
            return false;
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            $this->errors[] = $this->trans('Please upload a valid file.');
            return false;
        }

        $targetDir = rtrim(_PS_DOWNLOAD_DIR_, '/\\') . '/mpmassimport/';
        if (!$this->ensureDirectory($targetDir)) {
            $this->errors[] = $this->trans('Upload directory is not writable:') . ' ' . $targetDir;
            return false;
        }

        $safeBase = preg_replace('/[^a-z0-9._-]/i', '_', $originalName);
        $safeBase = trim((string) $safeBase);
        if ($safeBase === '' || $safeBase === '.' || $safeBase === '..') {
            $safeBase = 'upload.' . $ext;
        }

        $targetPath = rtrim($targetDir, '/\\') . '/' . $safeBase;
        if (strtolower(pathinfo($targetPath, PATHINFO_EXTENSION)) !== $ext) {
            $targetPath .= '.' . $ext;
        }

        if (is_file($targetPath)) {
            @unlink($targetPath);
        }
        if (!@move_uploaded_file($tmpName, $targetPath)) {
            $this->errors[] = $this->trans('Unable to move uploaded file.');
            return false;
        }

        $this->setSavedFilePermissions($targetPath);

        return [
            'name' => $originalName,
            'path' => $targetPath,
            'type' => $ext,
        ];
    }

    private function readFileHeaders(string $filePath, string $type, array $csvSettings = []): array
    {
        $type = strtolower($type);
        if ($type === 'csv') {
            $delimiter = isset($csvSettings['delimiter']) && is_string($csvSettings['delimiter']) && $csvSettings['delimiter'] !== '' ? $csvSettings['delimiter'][0] : ',';
            $enclosure = isset($csvSettings['enclosure']) && is_string($csvSettings['enclosure']) && $csvSettings['enclosure'] !== '' ? $csvSettings['enclosure'][0] : '"';
            $escape = isset($csvSettings['escape']) && is_string($csvSettings['escape']) && $csvSettings['escape'] !== '' ? $csvSettings['escape'][0] : '\\';
            return $this->readCsvHeaders($filePath, $delimiter, $enclosure, $escape);
        }

        return $this->readXlsxHeadersPartial($filePath);
    }

    private function normalizeHeaders(array $headers): array
    {
        $headers = array_map(function ($v) {
            $v = is_scalar($v) ? (string) $v : '';
            $v = trim($v);
            return $v;
        }, $headers);

        return array_values(array_filter($headers, function ($v) {
            return $v !== '';
        }));
    }

    private function readFilePreview(string $filePath, string $type, int $maxRows = 10, array $csvSettings = []): array
    {
        $type = strtolower($type);
        if ($type === 'csv') {
            $delimiter = isset($csvSettings['delimiter']) && is_string($csvSettings['delimiter']) && $csvSettings['delimiter'] !== '' ? $csvSettings['delimiter'][0] : ',';
            $enclosure = isset($csvSettings['enclosure']) && is_string($csvSettings['enclosure']) && $csvSettings['enclosure'] !== '' ? $csvSettings['enclosure'][0] : '"';
            $escape = isset($csvSettings['escape']) && is_string($csvSettings['escape']) && $csvSettings['escape'] !== '' ? $csvSettings['escape'][0] : '\\';
            return $this->readCsvPreview($filePath, $maxRows, $delimiter, $enclosure, $escape);
        }

        return $this->readXlsxPreviewPartial($filePath, $maxRows);
    }

    private function readCsvHeaders(string $filePath, string $delimiter, string $enclosure, string $escape): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException('File not readable');
        }

        if ($delimiter === '') {
            $delimiter = $this->detectCsvDelimiter($filePath);
        }
        $fp = fopen($filePath, 'rb');
        if (!$fp) {
            throw new \RuntimeException('Unable to read CSV');
        }

        $row = fgetcsv($fp, 0, $delimiter, $enclosure, $escape);
        fclose($fp);

        if (!is_array($row)) {
            return [];
        }

        return $this->normalizeHeaders($row);
    }

    private function readCsvPreview(string $filePath, int $maxRows, string $delimiter, string $enclosure, string $escape): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException('File not readable');
        }

        if ($delimiter === '') {
            $delimiter = $this->detectCsvDelimiter($filePath);
        }
        $fp = fopen($filePath, 'rb');
        if (!$fp) {
            throw new \RuntimeException('Unable to read CSV');
        }

        $headers = fgetcsv($fp, 0, $delimiter, $enclosure, $escape);
        if (!is_array($headers)) {
            fclose($fp);
            return [];
        }

        $preview = [];
        $rows = 0;
        while (!feof($fp) && $rows < $maxRows) {
            $r = fgetcsv($fp, 0, $delimiter, $enclosure, $escape);
            if ($r === false) {
                break;
            }
            if (!is_array($r)) {
                continue;
            }
            $preview[] = array_map(function ($v) {
                return is_scalar($v) ? trim((string) $v) : '';
            }, $r);
            $rows++;
        }
        fclose($fp);

        return $preview;
    }

    private function readXlsxHeadersPartial(string $filePath): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException('File not readable');
        }

        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $reader->setReadDataOnly(true);

        $filter = new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
            public function readCell($column, $row, $worksheetName = ''): bool
            {
                return (int) $row === 1;
            }
        };
        $reader->setReadFilter($filter);

        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $highestColumn = $sheet->getHighestColumn();
        $range = 'A1:' . $highestColumn . '1';
        $row = $sheet->rangeToArray($range, null, true, true, false);
        $headers = $row[0] ?? [];

        return $this->normalizeHeaders($headers);
    }

    private function readXlsxPreviewPartial(string $filePath, int $maxRows): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException('File not readable');
        }

        $maxRows = max(1, $maxRows);

        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $reader->setReadDataOnly(true);

        $filter = new class($maxRows) implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
            private int $lastRow;

            public function __construct(int $maxRows)
            {
                $this->lastRow = 1 + $maxRows;
            }

            public function readCell($column, $row, $worksheetName = ''): bool
            {
                $row = (int) $row;
                return $row >= 1 && $row <= $this->lastRow;
            }
        };
        $reader->setReadFilter($filter);

        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $highestColumn = $sheet->getHighestColumn();
        $highestRow = min((int) $sheet->getHighestRow(), 1 + $maxRows);
        if ($highestRow < 2) {
            return [];
        }

        $range = 'A2:' . $highestColumn . $highestRow;
        $rows = $sheet->rangeToArray($range, '', true, true, false);

        $preview = [];
        foreach ($rows as $r) {
            $preview[] = array_map(function ($v) {
                if (is_bool($v)) {
                    return $v ? '1' : '0';
                }
                if (is_scalar($v)) {
                    return trim((string) $v);
                }
                return '';
            }, $r);
        }

        return $preview;
    }

    private function detectCsvDelimiter(string $filePath): string
    {
        $sample = @file_get_contents($filePath, false, null, 0, 4096);
        if (!is_string($sample) || $sample === '') {
            return ',';
        }

        $delims = [',', ';', "\t", '|'];
        $best = ',';
        $bestCount = -1;

        foreach ($delims as $d) {
            $count = substr_count($sample, $d);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $d;
            }
        }

        return $best;
    }

    private function downloadFromUrl(string $url): array
    {
        if (!preg_match('#^https?://#i', $url)) {
            throw new \InvalidArgumentException('Invalid URL');
        }

        $downloadDir = rtrim(_PS_DOWNLOAD_DIR_, '/\\') . '/mpmassimport';
        if (!$this->ensureDirectory($downloadDir)) {
            throw new \RuntimeException('Download directory is not writable: ' . $downloadDir);
        }

        $path = parse_url($url, PHP_URL_PATH);
        $basename = is_string($path) ? basename($path) : '';
        if ($basename === '' || $basename === '/' || $basename === '.') {
            $basename = 'download';
        }

        $resolvedFilename = '';
        $headers = [];

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('cURL not available');
        }

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $line) use (&$headers, &$resolvedFilename) {
            $len = strlen($line);
            $lineTrim = trim($line);
            if ($lineTrim === '') {
                return $len;
            }

            $parts = explode(':', $lineTrim, 2);
            if (count($parts) === 2) {
                $name = strtolower(trim($parts[0]));
                $value = trim($parts[1]);
                $headers[$name][] = $value;
                if ($name === 'content-disposition' && $resolvedFilename === '') {
                    $resolvedFilename = $this->extractFilenameFromContentDisposition($value);
                }
            }
            return $len;
        });

        $safeName = $this->sanitizeDownloadFilename($resolvedFilename !== '' ? $resolvedFilename : $basename);
        $target = $this->makeUniquePath($downloadDir, $safeName);

        $fp = fopen($target, 'wb');
        if (!$fp) {
            throw new \RuntimeException('Unable to write download');
        }

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; PrestaShop MpMassImportProducts/1.0; +' . _PS_BASE_URL_ . ')');
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        $ok = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        fclose($fp);

        $this->setSavedFilePermissions($target);

        if (!$ok || $code >= 400) {
            @unlink($target);
            $suffix = '';
            if ($effectiveUrl !== '' && $effectiveUrl !== $url) {
                $suffix = ' (effective URL: ' . $effectiveUrl . ')';
            }
            throw new \RuntimeException('Download failed: ' . ($err ?: (string) $code) . $suffix);
        }

        // If the server provided a better filename after redirects, rename the file.
        if ($resolvedFilename === '' && $effectiveUrl !== '') {
            $effectivePath = parse_url($effectiveUrl, PHP_URL_PATH);
            $effectiveBase = is_string($effectivePath) ? basename($effectivePath) : '';
            if ($effectiveBase !== '' && $effectiveBase !== '/' && $effectiveBase !== '.') {
                $effectiveSafe = $this->sanitizeDownloadFilename($effectiveBase);
                if ($effectiveSafe !== '' && $effectiveSafe !== basename($target)) {
                    $newTarget = $this->makeUniquePath($downloadDir, $effectiveSafe);
                    if (@rename($target, $newTarget)) {
                        $target = $newTarget;
                        $basename = $effectiveBase;
                        $this->setSavedFilePermissions($target);
                    }
                }
            }
        } elseif ($resolvedFilename !== '') {
            $basename = $resolvedFilename;
        }

        $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

        if ($ext === 'zip') {
            return [
                'name' => $basename,
                'path' => $target,
                'type' => 'zip',
            ];
        }

        if (!in_array($ext, ['xlsx', 'csv'], true)) {
            throw new \RuntimeException('Unsupported downloaded file type');
        }

        return [
            'name' => $basename,
            'path' => $target,
            'type' => $ext,
        ];
    }

    private function extractFilenameFromContentDisposition(string $headerValue): string
    {
        // Examples:
        // attachment; filename="file.xlsx"
        // attachment; filename*=UTF-8''file%20name.xlsx
        $v = trim($headerValue);
        if ($v === '') {
            return '';
        }

        if (preg_match("/filename\*=\s*([^']+)''([^;]+)/i", $v, $m)) {
            $encoded = trim((string) ($m[2] ?? ''));
            if ($encoded !== '') {
                $decoded = rawurldecode($encoded);
                return trim($decoded, " \t\n\r\0\v\"");
            }
        }

        if (preg_match('/filename\s*=\s*"([^"]+)"/i', $v, $m)) {
            return trim((string) ($m[1] ?? ''));
        }

        if (preg_match('/filename\s*=\s*([^;]+)/i', $v, $m)) {
            $name = trim((string) ($m[1] ?? ''));
            return trim($name, " \t\n\r\0\v\"");
        }

        return '';
    }

    private function sanitizeDownloadFilename(string $name): string
    {
        $name = trim($name);
        if ($name === '' || $name === '/' || $name === '.' || $name === '..') {
            return 'download';
        }
        $name = basename($name);
        $name = preg_replace('/[^a-z0-9._-]/i', '_', $name);
        if (!is_string($name) || $name === '' || $name === '.' || $name === '..') {
            return 'download';
        }
        return $name;
    }

    private function makeUniquePath(string $dir, string $filename): string
    {
        $dir = rtrim($dir, '/\\');
        $filename = $this->sanitizeDownloadFilename($filename);
        $path = $dir . '/' . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
        return $path;
    }

    private function listZipSupportedEntries(string $zipPath): array
    {
        $zip = new \ZipArchive();
        $open = $zip->open($zipPath);
        if ($open !== true) {
            throw new \RuntimeException('Unable to open ZIP');
        }

        $supported = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (!is_string($entry) || $entry === '') {
                continue;
            }
            $entry = str_replace('\\', '/', $entry);
            if (str_contains($entry, '../') || str_starts_with($entry, '/')) {
                continue;
            }
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (in_array($ext, ['csv', 'xlsx'], true)) {
                $supported[] = $entry;
            }
        }

        $zip->close();
        sort($supported);
        return $supported;
    }

    private function extractZipEntryToDownloadDir(string $zipPath, string $downloadDir, string $entry): array
    {
        $zip = new \ZipArchive();
        $open = $zip->open($zipPath);
        if ($open !== true) {
            throw new \RuntimeException('Unable to open ZIP');
        }

        $entry = str_replace('\\', '/', $entry);
        if ($entry === '' || str_contains($entry, '../') || str_starts_with($entry, '/')) {
            throw new \RuntimeException('Invalid ZIP entry');
        }
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx'], true)) {
            throw new \RuntimeException('Unsupported ZIP entry');
        }

        $stream = $zip->getStream($entry);
        if ($stream === false) {
            $zip->close();
            throw new \RuntimeException('Unable to read ZIP entry');
        }

        $outName = $this->sanitizeDownloadFilename(basename($entry));
        $outPath = rtrim($downloadDir, '/\\') . '/' . $outName;
        if (is_file($outPath)) {
            @unlink($outPath);
        }

        $out = fopen($outPath, 'wb');
        if (!$out) {
            fclose($stream);
            $zip->close();
            throw new \RuntimeException('Unable to write extracted file');
        }

        while (!feof($stream)) {
            $buf = fread($stream, 8192);
            if ($buf === false) {
                break;
            }
            fwrite($out, $buf);
        }
        fclose($out);
        fclose($stream);
        $zip->close();

        if (!is_file($outPath) || !is_readable($outPath)) {
            throw new \RuntimeException('Extracted file not readable');
        }

        $this->setSavedFilePermissions($outPath);

        $ext = strtolower(pathinfo($outPath, PATHINFO_EXTENSION));

        return [
            'name' => basename($outPath),
            'path' => $outPath,
            'type' => $ext,
        ];
    }

    private function setSavedFilePermissions(string $path): void
    {
        if ($path === '' || !is_file($path)) {
            return;
        }
        @chmod($path, 0775);
    }

    private function buildEffectiveMapping(array $headers, array $savedMapping, array $autoMapping): array
    {
        $effective = [];

        foreach ($headers as $header) {
            if (!is_string($header)) {
                continue;
            }

            if (isset($savedMapping[$header])) {
                $effective[$header] = $savedMapping[$header];
                continue;
            }

            if (isset($autoMapping[$header])) {
                $effective[$header] = $autoMapping[$header];
                continue;
            }

            $effective[$header] = '';
        }

        return $effective;
    }

    private function getMappingTemplates(): array
    {
        $raw = (string) \Configuration::get('MPMASSIMPORTPRODUCTS_MAPPING_TEMPLATES');
        $templates = json_decode($raw, true);
        if (!is_array($templates)) {
            $templates = [];
        }

        foreach ($templates as $name => $tpl) {
            if (!is_string($name) || $name === '' || !is_array($tpl)) {
                unset($templates[$name]);
                continue;
            }
            if (!isset($tpl['mapping']) || !is_array($tpl['mapping'])) {
                $templates[$name]['mapping'] = [];
            }
        }

        ksort($templates);
        return $templates;
    }

    private function saveMappingTemplates(array $templates): void
    {
        \Configuration::updateValue('MPMASSIMPORTPRODUCTS_MAPPING_TEMPLATES', json_encode($templates));
    }

    private function formatMappingTemplatesForSelect(array $templates): array
    {
        $options = [];
        foreach ($templates as $name => $tpl) {
            $options[] = [
                'value' => $name,
                'label' => $name,
            ];
        }
        return $options;
    }

    private function getProductFieldOptions(): array
    {
        $fields = [];

        $fields[] = [
            'value' => 'id_product',
            'label' => 'id_product',
        ];

        $fields[] = [
            'value' => '__CATEGORY__',
            'label' => $this->trans('CATEGORIA'),
        ];
        $fields[] = [
            'value' => '__ATTRIBUTE__',
            'label' => $this->trans('ATTRIBUTO'),
        ];
        $fields[] = [
            'value' => '__FEATURE__',
            'label' => $this->trans('CARATTERISTICA'),
        ];
        $fields[] = [
            'value' => '__STOCK__',
            'label' => $this->trans('MAGAZZINO'),
        ];
        $fields[] = [
            'value' => '__IMAGES__',
            'label' => $this->trans('IMMAGINI'),
        ];

        if (isset(\Product::$definition['fields']) && is_array(\Product::$definition['fields'])) {
            foreach (\Product::$definition['fields'] as $fieldName => $def) {
                $label = $fieldName;
                if (isset($def['lang']) && $def['lang']) {
                    $label .= ' (lang)';
                }
                $fields[] = [
                    'value' => $fieldName,
                    'label' => $label,
                ];
            }
        }

        usort($fields, function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        array_unshift($fields, [
            'value' => '',
            'label' => $this->trans('— Do not import —'),
        ]);

        return $fields;
    }

    private function buildAutoMapping(array $headers): array
    {
        $auto = [];

        $productFields = [];
        if (isset(\Product::$definition['fields']) && is_array(\Product::$definition['fields'])) {
            foreach (array_keys(\Product::$definition['fields']) as $fieldName) {
                $productFields[$this->normalizeHeaderKey($fieldName)] = $fieldName;
            }
        }

        $aliases = $this->getHeaderAliases();
        $stockHeaderKeys = array_fill_keys([
            $this->normalizeHeaderKey('qty'),
            $this->normalizeHeaderKey('quantity'),
            $this->normalizeHeaderKey('quantita'),
            $this->normalizeHeaderKey('quantità'),
            $this->normalizeHeaderKey('stock'),
            $this->normalizeHeaderKey('magazzino'),
            $this->normalizeHeaderKey('giacenza'),
            $this->normalizeHeaderKey('disponibilita'),
            $this->normalizeHeaderKey('disponibilità'),
        ], true);

        foreach ($headers as $header) {
            if (!is_string($header) || trim($header) === '') {
                continue;
            }
            $h = trim($header);

            if (strcasecmp($h, 'CATEGORIES') === 0) {
                $auto[$header] = '__CATEGORY__';
                continue;
            }
            if (stripos($h, 'A:') === 0) {
                $auto[$header] = '__ATTRIBUTE__';
                continue;
            }
            if (stripos($h, 'F:') === 0) {
                $auto[$header] = '__FEATURE__';
                continue;
            }

            if (strcasecmp($h, 'IMAGE') === 0 || strcasecmp($h, 'IMAGES') === 0) {
                $auto[$header] = '__IMAGES__';
                continue;
            }

            $key = $this->normalizeHeaderKey($h);

            if (isset($stockHeaderKeys[$key])) {
                $auto[$header] = '__STOCK__';
                continue;
            }

            if (isset($aliases[$key])) {
                $candidate = $aliases[$key];
                if (is_string($candidate) && $candidate !== '' && isset($productFields[$this->normalizeHeaderKey($candidate)])) {
                    $auto[$header] = $candidate;
                    continue;
                }
            }

            if (isset($productFields[$key])) {
                $auto[$header] = $productFields[$key];
            }
        }

        return $auto;
    }

    private function buildHeaderMeta(array $headers): array
    {
        $meta = [];

        foreach ($headers as $header) {
            if (!is_string($header) || trim($header) === '') {
                continue;
            }

            $h = trim($header);
            $meta[$header] = [
                'type' => 'normal',
                'name' => '',
            ];

            if (strcasecmp($h, 'CATEGORIES') === 0) {
                $meta[$header]['type'] = 'category';
                $meta[$header]['name'] = 'CATEGORIES';
                continue;
            }

            if (stripos($h, 'A:') === 0) {
                $meta[$header]['type'] = 'attribute';
                $meta[$header]['name'] = trim(substr($h, 2));
                continue;
            }

            if (stripos($h, 'F:') === 0) {
                $meta[$header]['type'] = 'feature';
                $meta[$header]['name'] = trim(substr($h, 2));
                continue;
            }

            if (strcasecmp($h, 'IMAGE') === 0 || strcasecmp($h, 'IMAGES') === 0) {
                $meta[$header]['type'] = 'image';
                $meta[$header]['name'] = 'IMAGE';
                continue;
            }
        }

        return $meta;
    }

    private function getHeaderAliases(): array
    {
        return [
            $this->normalizeHeaderKey('id') => 'id_product',
            $this->normalizeHeaderKey('product id') => 'id_product',
            $this->normalizeHeaderKey('id prodotto') => 'id_product',
            $this->normalizeHeaderKey('name') => 'name',
            $this->normalizeHeaderKey('nome') => 'name',
            $this->normalizeHeaderKey('nome prodotto') => 'name',
            $this->normalizeHeaderKey('titolo') => 'name',
            $this->normalizeHeaderKey('description') => 'description',
            $this->normalizeHeaderKey('descrizione') => 'description',
            $this->normalizeHeaderKey('descrizione breve') => 'description_short',
            $this->normalizeHeaderKey('short description') => 'description_short',
            $this->normalizeHeaderKey('reference') => 'reference',
            $this->normalizeHeaderKey('sku') => 'reference',
            $this->normalizeHeaderKey('codice') => 'reference',
            $this->normalizeHeaderKey('codice prodotto') => 'reference',
            $this->normalizeHeaderKey('ean') => 'ean13',
            $this->normalizeHeaderKey('ean13') => 'ean13',
            $this->normalizeHeaderKey('barcode') => 'ean13',
            $this->normalizeHeaderKey('codice a barre') => 'ean13',
            $this->normalizeHeaderKey('upc') => 'upc',
            $this->normalizeHeaderKey('mpn') => 'mpn',
            $this->normalizeHeaderKey('isbn') => 'isbn',
            $this->normalizeHeaderKey('price') => 'price',
            $this->normalizeHeaderKey('prezzo') => 'price',
            $this->normalizeHeaderKey('prezzo iva esclusa') => 'price',
            $this->normalizeHeaderKey('net price') => 'price',
            $this->normalizeHeaderKey('wholesale price') => 'wholesale_price',
            $this->normalizeHeaderKey('prezzo acquisto') => 'wholesale_price',
            $this->normalizeHeaderKey('costo') => 'wholesale_price',
            $this->normalizeHeaderKey('quantity') => 'quantity',
            $this->normalizeHeaderKey('qty') => 'quantity',
            $this->normalizeHeaderKey('qta') => 'quantity',
            $this->normalizeHeaderKey('giacenza') => 'quantity',
            $this->normalizeHeaderKey('weight') => 'weight',
            $this->normalizeHeaderKey('peso') => 'weight',
            $this->normalizeHeaderKey('active') => 'active',
            $this->normalizeHeaderKey('attivo') => 'active',
            $this->normalizeHeaderKey('visibile') => 'active',
            $this->normalizeHeaderKey('default category') => 'id_category_default',
            $this->normalizeHeaderKey('categoria default') => 'id_category_default',
            $this->normalizeHeaderKey('categoria predefinita') => 'id_category_default',
            $this->normalizeHeaderKey('manufacturer') => 'id_manufacturer',
            $this->normalizeHeaderKey('brand') => 'id_manufacturer',
            $this->normalizeHeaderKey('marca') => 'id_manufacturer',
            $this->normalizeHeaderKey('produttore') => 'id_manufacturer',
            $this->normalizeHeaderKey('supplier') => 'id_supplier',
            $this->normalizeHeaderKey('fornitore') => 'id_supplier',
            $this->normalizeHeaderKey('tax rules group') => 'id_tax_rules_group',
            $this->normalizeHeaderKey('iva') => 'id_tax_rules_group',
            $this->normalizeHeaderKey('aliquota iva') => 'id_tax_rules_group',
            $this->normalizeHeaderKey('link rewrite') => 'link_rewrite',
            $this->normalizeHeaderKey('slug') => 'link_rewrite',
            $this->normalizeHeaderKey('url') => 'link_rewrite',
            $this->normalizeHeaderKey('image') => '__IMAGES__',
            $this->normalizeHeaderKey('images') => '__IMAGES__',
            $this->normalizeHeaderKey('immagine') => '__IMAGES__',
            $this->normalizeHeaderKey('immagini') => '__IMAGES__',
        ];
    }

    private function parseNumber($value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (!is_scalar($value)) {
            return null;
        }

        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }

        $s = str_replace([' ', '\u{00A0}'], '', $s);
        $s = str_replace(',', '.', $s);
        $s = preg_replace('/[^0-9.\-]/', '', $s);
        if (!is_string($s) || $s === '' || $s === '-' || $s === '.' || $s === '-.') {
            return null;
        }

        return (float) $s;
    }

    private function computeMarkup(float $basePrice, array $rules, float $taxRate = 0.0): array
    {
        $matched = null;
        foreach ($rules as $r) {
            if (!is_array($r)) {
                continue;
            }
            if (!isset($r['min'])) {
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

        // Determine the starting price we run the operations on.
        $working = $basePrice;
        if ($vatMode === 'si') {
            $working = $basePrice * $taxFactor;
        } elseif ($vatMode === 'scorpora') {
            $working = $taxRate > 0 ? ($basePrice / $taxFactor) : $basePrice;
        }

        // Apply markup
        $after = $working;
        if ($type === 'fixed') {
            $after = $working + $value;
        } else {
            $after = $working * (1 + ($value / 100));
        }

        // Apply rounding to final price (on the working price)
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

        // Convert result back to tax-excluded final price.
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

    private function getTaxRulesGroupOptions(): array
    {
        $options = [];

        $rows = \TaxRulesGroup::getTaxRulesGroups(true);
        if (!is_array($rows)) {
            $rows = [];
        }

        $options[] = [
            'id' => 0,
            'label' => $this->trans('No tax'),
            'rate' => 0.0,
        ];

        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $id = (int) ($r['id_tax_rules_group'] ?? 0);
            $name = (string) ($r['name'] ?? '');
            if ($id <= 0 || $name === '') {
                continue;
            }
            $rate = $this->getTaxRateForGroup($id);
            $options[] = [
                'id' => $id,
                'label' => $name,
                'rate' => $rate,
            ];
        }

        usort($options, function ($a, $b) {
            return strcmp((string) $a['label'], (string) $b['label']);
        });

        return $options;
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

    private function getCategoryOptions(): array
    {
        $idLang = (int) $this->context->language->id;

        $options = [];
        $options[] = [
            'id' => 0,
            'label' => $this->trans('— None —'),
        ];

        $cats = \Category::getCategories($idLang, true, false);
        if (!is_array($cats)) {
            $cats = [];
        }

        foreach ($cats as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id_category'] ?? 0);
            $name = (string) ($row['name'] ?? '');
            if ($id <= 0 || $name === '') {
                continue;
            }
            $options[] = [
                'id' => $id,
                'label' => $name,
            ];
        }

        usort($options, function ($a, $b) {
            return strcmp((string) $a['label'], (string) $b['label']);
        });

        return $options;
    }

    private function getManufacturerOptions(): array
    {
        $idLang = (int) $this->context->language->id;

        $options = [];
        $options[] = [
            'id' => 0,
            'label' => $this->trans('— None —'),
        ];

        $rows = \Manufacturer::getManufacturers(false, $idLang, false);
        if (!is_array($rows)) {
            $rows = [];
        }

        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $id = (int) ($r['id_manufacturer'] ?? 0);
            $name = (string) ($r['name'] ?? '');
            if ($id <= 0 || $name === '') {
                continue;
            }
            $options[] = [
                'id' => $id,
                'label' => $name,
            ];
        }

        usort($options, function ($a, $b) {
            return strcmp((string) $a['label'], (string) $b['label']);
        });

        return $options;
    }

    private function getSupplierOptions(): array
    {
        $idLang = (int) $this->context->language->id;

        $options = [];
        $options[] = [
            'id' => 0,
            'label' => $this->trans('— None —'),
        ];

        $rows = \Supplier::getSuppliers(false, $idLang, false);
        if (!is_array($rows)) {
            $rows = [];
        }

        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $id = (int) ($r['id_supplier'] ?? 0);
            $name = (string) ($r['name'] ?? '');
            if ($id <= 0 || $name === '') {
                continue;
            }
            $options[] = [
                'id' => $id,
                'label' => $name,
            ];
        }

        usort($options, function ($a, $b) {
            return strcmp((string) $a['label'], (string) $b['label']);
        });

        return $options;
    }

    private function getLanguageOptions(): array
    {
        $rows = \Language::getLanguages(true);
        if (!is_array($rows)) {
            $rows = [];
        }

        $options = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $id = (int) ($r['id_lang'] ?? 0);
            $name = (string) ($r['name'] ?? '');
            if ($id <= 0 || $name === '') {
                continue;
            }
            $options[] = [
                'id' => $id,
                'name' => $name,
            ];
        }

        usort($options, function ($a, $b) {
            return strcmp((string) $a['name'], (string) $b['name']);
        });

        return $options;
    }

    private function validatePreviewRows(array $headers, array $rows, array $rulesByHeader): array
    {
        $maxErrors = 20;
        $report = [
            'invalid_rows' => 0,
            'errors' => [],
        ];

        $headerIndexes = [];
        foreach ($headers as $idx => $h) {
            if (is_string($h) && $h !== '') {
                $headerIndexes[$h] = (int) $idx;
            }
        }

        foreach ($rows as $rowIndex0 => $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowOk = true;
            foreach ($rulesByHeader as $header => $rule) {
                if (!is_string($header) || $header === '') {
                    continue;
                }
                if (!isset($headerIndexes[$header])) {
                    continue;
                }

                if (!is_array($rule)) {
                    continue;
                }

                $idx = $headerIndexes[$header];
                $value = $row[$idx] ?? null;

                $check = $this->validateValueAgainstRule($value, $rule);
                if ($check !== true) {
                    $rowOk = false;
                    if (count($report['errors']) < $maxErrors) {
                        $report['errors'][] = [
                            'row' => (int) $rowIndex0 + 1,
                            'header' => $header,
                            'message' => $check,
                        ];
                    }
                    break;
                }
            }

            if (!$rowOk) {
                $report['invalid_rows']++;
            }
        }

        return $report;
    }

    private function validateValueAgainstRule($value, array $rule)
    {
        $type = (string) ($rule['type'] ?? 'none');
        $type = strtolower(trim($type));
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
            if ($min === null || $max === null) {
                return 'Invalid range';
            }
            if ($min > $max) {
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

    private function normalizeHeaderKey(string $value): string
    {
        $value = trim($value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/i', '', $value);
        return (string) $value;
    }

    public function _renderForm()
    {
        $this->fields_form = [
            'legend' => [
                'title' => $this->trans('Product'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->trans('Location'),
                    'name' => 'location',
                    'required' => true,
                ],
            ],
            'submit' => [
                'title' => $this->trans('Save'),
            ],
        ];

        return parent::renderForm();
    }

    protected function response($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function ajaxProcessMpMassImportProductsImportChunk()
    {
        try {
            $templateName = (string) Tools::getValue('template_name');
            $offset = (int) Tools::getValue('offset', 0);
            $limit = (int) Tools::getValue('limit', 25);

            $postedFilePath = (string) Tools::getValue('file_path', '');
            $postedFileType = (string) Tools::getValue('file_type', '');

            $defaults = Tools::getValue('defaults');
            if (!is_array($defaults)) {
                $defaults = [];
            }
            $priceSettings = Tools::getValue('price_settings');
            if (!is_array($priceSettings)) {
                $priceSettings = [];
            }
            $resetProducts = Tools::getValue('resetProducts');
            if (!is_array($resetProducts)) {
                $resetProducts = [];
            }

            $csvSettings = Tools::getValue('csv_settings');
            if (!is_array($csvSettings)) {
                $csvSettingsRaw = (string) \Configuration::get(self::CSV_SETTINGS);
                $csvSettings = json_decode($csvSettingsRaw, true);
                if (!is_array($csvSettings)) {
                    $csvSettings = [
                        'delimiter' => ',',
                        'enclosure' => '"',
                        'escape' => '\\',
                    ];
                }
            }

            $templatesRaw = (string) \Configuration::get('MPMASSIMPORTPRODUCTS_MAPPING_TEMPLATES');
            $templates = json_decode($templatesRaw, true);
            if (!is_array($templates)) {
                $templates = [];
            }
            $tpl = isset($templates[$templateName]) && is_array($templates[$templateName]) ? $templates[$templateName] : [];
            $tplFile = isset($tpl['file']) && is_array($tpl['file']) ? $tpl['file'] : [];
            $tplFilePath = isset($tplFile['path']) && is_string($tplFile['path']) ? $tplFile['path'] : '';
            $tplFileType = isset($tplFile['type']) && is_string($tplFile['type']) ? $tplFile['type'] : '';

            $lastFileRaw = (string) \Configuration::get('MPMASSIMPORTPRODUCTS_LAST_XLSX');
            $lastFile = json_decode($lastFileRaw, true);
            if (!is_array($lastFile)) {
                $lastFile = [];
            }
            $lastFilePath = isset($lastFile['path']) && is_string($lastFile['path']) ? $lastFile['path'] : '';
            $lastFileType = isset($lastFile['type']) && is_string($lastFile['type']) ? $lastFile['type'] : '';

            $filePath = $tplFilePath !== '' ? $tplFilePath : ($postedFilePath !== '' ? $postedFilePath : $lastFilePath);
            $type = $tplFileType !== '' ? $tplFileType : ($postedFileType !== '' ? $postedFileType : $lastFileType);

            if ($filePath === '' || !is_file($filePath) || !is_readable($filePath)) {
                $this->response([
                    'done' => true,
                    'nextOffset' => $offset,
                    'stats' => ['processed' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => 1],
                    'errors' => ['File not readable: ' . $filePath . ' (type=' . $type . ')'],
                ]);
            }
            if ($templateName === '') {
                $this->response([
                    'done' => true,
                    'nextOffset' => $offset,
                    'stats' => ['processed' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => 1],
                    'errors' => ['Template not selected'],
                ]);
            }

            $manager = new \MpSoft\MpMassImportProducts\Helpers\ProductImportManager($this->module, $this->context);
            $template = $manager->loadTemplate($templateName);
            $result = $manager->importChunk([
                'filePath' => $filePath,
                'type' => $type,
                'template_name' => $templateName,
                'template' => $template,
                'offset' => $offset,
                'limit' => $limit,
                'defaults' => $defaults,
                'price_settings' => $priceSettings,
                'resetProducts' => $resetProducts,
                'csv_settings' => $csvSettings,
            ]);

            $this->response($result);
        } catch (\Throwable $e) {
            $this->response([
                'done' => true,
                'nextOffset' => (int) Tools::getValue('offset', 0),
                'stats' => ['processed' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => 1],
                'errors' => [$e->getMessage()],
            ]);
        }
    }
}
