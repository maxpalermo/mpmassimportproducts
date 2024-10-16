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

namespace MpSoft\MpMassImportProducts\Product;
use MpSoft\MpMassImportProducts\Product\Enum\Condition;
use MpSoft\MpMassImportProducts\Product\Enum\ProductType;
use MpSoft\MpMassImportProducts\Product\Enum\RedirectType;
use MpSoft\MpMassImportProducts\Product\Enum\Visibility;

abstract class AbstractProduct
{
    protected $id_product;
    protected $id_supplier;
    protected $id_manufacturer;
    protected $id_category_default;
    protected $id_shop_default;
    protected $id_tax_rules_group;
    protected $on_sale;
    protected $online_only;
    protected $ean13;
    protected $isbn;
    protected $upc;
    protected $mpn;
    protected $ecotax;
    protected $quantity;
    protected $minimal_quantity;
    protected $low_stock_threshold;
    protected $low_stock_alert;
    protected $price;
    protected $wholesale_price;
    protected $unity;
    protected $unit_price;
    protected $unit_price_ratio;
    protected $additional_shipping_cost;
    protected $reference;
    protected $supplier_reference;
    protected $location;
    protected $width;
    protected $height;
    protected $depth;
    protected $weight;
    protected $out_of_stock;
    protected $additional_delivery_times;
    protected $quantity_discount;
    protected $customizable;
    protected $uploadable_files;
    protected $text_fields;
    protected $active;
    protected $redirect_type;
    protected $id_type_redirected;
    protected $available_for_order;
    protected $available_date;
    protected $show_condition;
    protected $condition;
    protected $show_price;
    protected $indexed;
    protected $visibility;
    protected $cache_is_pack;
    protected $cache_has_attachments;
    protected $is_virtual;
    protected $cache_default_attribute;
    protected $date_add;
    protected $date_upd;
    protected $advanced_stock_management;
    protected $pack_stock_type;
    protected $state;
    protected $product_type;

    // ****** LANGUAGE FIELDS ******
    protected $description;
    protected $description_short;
    protected $link_rewrite;
    protected $meta_description;
    protected $meta_keywords;
    protected $meta_title;
    protected $name;
    protected $available_now;
    protected $available_later;
    protected $delivery_in_stock;
    protected $delivery_out_stock;

    // ****** PRODUCT OBJECT ******
    protected $id;
    protected $product;

    // ****** RESULT ******
    protected $result_id;
    protected $error;
    protected $context;
    protected $translator;

    public function __construct($id = null, $id_lang = null, $id_shop = null)
    {
        $this->id = $id;
        $this->product = new \Product($id, true, $id_lang, $id_shop);
        $this->context = \Context::getContext();
        $this->translator = $this->context->getTranslator();
    }

    abstract public function import();

    public function insert($force_id = false)
    {
        $this->product->id = (int) $this->id;
        $this->product->id_supplier = (int) $this->id_supplier;
        $this->product->id_manufacturer = (int) $this->id_manufacturer;
        $this->product->id_category_default = (int) $this->id_category_default;
        $this->product->id_shop_default = (int) $this->id_shop_default;
        $this->product->id_tax_rules_group = (int) $this->id_tax_rules_group;
        $this->product->on_sale = (int) $this->on_sale;
        $this->product->online_only = (int) $this->online_only;
        $this->product->ean13 = $this->ean13;
        $this->product->isbn = $this->isbn;
        $this->product->upc = $this->upc;
        $this->product->mpn = $this->mpn;
        $this->product->ecotax = $this->ecotax;
        $this->product->quantity = (int) $this->quantity;
        $this->product->minimal_quantity = (int) $this->minimal_quantity;
        $this->product->low_stock_threshold = (int) $this->low_stock_threshold;
        $this->product->low_stock_alert = (int) $this->low_stock_alert;
        $this->product->price = (float) $this->price;
        $this->product->wholesale_price = (float) $this->wholesale_price;
        $this->product->unity = $this->unity;
        $this->product->unit_price = (float) $this->unit_price;
        $this->product->unit_price_ratio = (float) $this->unit_price_ratio;
        $this->product->additional_shipping_cost = (float) $this->additional_shipping_cost;
        $this->product->reference = $this->reference;
        $this->product->supplier_reference = $this->supplier_reference;
        $this->product->location = $this->location;
        $this->product->width = (float) $this->width;
        $this->product->height = (float) $this->height;
        $this->product->depth = (float) $this->depth;
        $this->product->weight = (float) $this->weight;
        $this->product->out_of_stock = (int) $this->out_of_stock;
        $this->product->additional_delivery_times = (int) $this->additional_delivery_times;
        $this->product->quantity_discount = (int) $this->quantity_discount;
        $this->product->customizable = (int) $this->customizable;
        $this->product->uploadable_files = (int) $this->uploadable_files;
        $this->product->text_fields = (int) $this->text_fields;
        $this->product->active = (int) $this->active;
        $this->product->redirect_type = $this->redirect_type;
        $this->product->id_type_redirected = (int) $this->id_type_redirected;
        $this->product->available_for_order = (int) $this->available_for_order;
        $this->product->available_date = $this->available_date;
        $this->product->show_condition = (int) $this->show_condition;
        $this->product->condition = $this->condition;
        $this->product->show_price = (int) $this->show_price;
        $this->product->indexed = (int) $this->indexed;
        $this->product->visibility = $this->visibility;
        $this->product->cache_is_pack = (int) $this->cache_is_pack;
        $this->product->cache_has_attachments = (int) $this->cache_has_attachments;
        $this->product->is_virtual = (int) $this->is_virtual;
        $this->product->cache_default_attribute = (int) $this->cache_default_attribute;
        $this->product->date_add = $this->date_add;
        $this->product->date_upd = $this->date_upd;
        $this->product->advanced_stock_management = (int) $this->advanced_stock_management;
        $this->product->pack_stock_type = (int) $this->pack_stock_type;
        $this->product->state = $this->state;
        $this->product->product_type = $this->product_type;
        // ****** LANGUAGE FIELDS ******
        $languages = \Language::getLanguages(false);
        foreach ($languages as $language) {
            $id_lang = (int) $language['id_lang'];
            $this->setLangField('name', $this->name, $id_lang);
            $this->setLangField('description', $this->description, $id_lang);
            $this->setLangField('description_short', $this->description_short, $id_lang);
            $this->setLinkRewrite($this->link_rewrite, $this->name, $id_lang);
            $this->setLangField('meta_description', $this->meta_description, $id_lang);
            $this->setLangField('meta_keywords', $this->meta_keywords, $id_lang);
            $this->setLangField('meta_title', $this->meta_title, $id_lang);
            $this->setLangField('available_now', $this->available_now, $id_lang);
            $this->setLangField('available_later', $this->available_later, $id_lang);
            $this->setLangField('delivery_in_stock', $this->delivery_in_stock, $id_lang);
            $this->setLangField('delivery_out_stock', $this->delivery_out_stock, $id_lang);
        }

        try {
            if ($this->id && $force_id) {
                $this->product->force_id = true;
                $this->product->id = (int) $this->id;
                $this->result_id = $this->product->add();
            } elseif ($this->id) {
                $this->result_id = $this->product->update();
            } else {
                $this->result_id = $this->product->add();
            }
        } catch (\Throwable $th) {
            $this->result_id = false;
            $this->error = $th->getMessage();
        }

        return $this->product->id;
    }

    protected function setLinkRewrite($link_rewrite, $name, $id_lang)
    {
        $current_link_rewrite = '';
        $current_name = '';

        if ($link_rewrite && is_array($link_rewrite) && isset($link_rewrite[$id_lang])) {
            $current_link_rewrite = $link_rewrite[$id_lang];
        }

        if ($name && is_array($name) && isset($name[$id_lang])) {
            $current_name = $name[$id_lang];
        } elseif ($name) {
            $current_name = $name;
        } else {
            Throw new \Exception('Name is required to generate link rewrite');
        }
        $str2url = \Tools::str2url($current_name);

        if ($current_link_rewrite && $current_name) {
            if ($current_link_rewrite != $str2url) {
                $this->product->link_rewrite[$id_lang] = $str2url;
            } else {
                $this->product->link_rewrite[$id_lang] = $current_link_rewrite;
            }
        } else {
            $this->product->link_rewrite[$id_lang] = \Tools::str2url($name);
        }
    }

    protected function setLangField($field, $value, $id_lang)
    {
        if (is_array($value) && isset($value[$id_lang])) {
            $this->product->$field[$id_lang] = $value[$id_lang];
        } else {
            $this->product->$field[$id_lang] = $value;
        }
    }

    public function validateCondition($value)
    {
        return Condition::isValid($value);
    }

    public function getDefaultCondition()
    {
        return Condition::getDefault();
    }

    public function validateProductType($value)
    {
        return ProductType::isValid($value);
    }

    public function getDefaultProductType()
    {
        return ProductType::getDefault();
    }

    public function validateRedirectType($value)
    {
        return RedirectType::isValid($value);
    }

    public function getDefaultRedirectType()
    {
        return RedirectType::getDefault();
    }

    public function validateVisibility($value)
    {
        return Visibility::isValid($value);
    }

    public function getDefaultVisibility()
    {
        return Visibility::getDefault();
    }

    public function getError()
    {
        return $this->error;
    }

    public function getImageFromLink($link)
    {
        return \Tools::file_get_contents($link, false, null, 5);
    }

    public function insertImage($id_product, $content, $cover = false)
    {
        $imageObj = new \Image();
        $imageObj->id_product = $id_product;
        $imageObj->position = \Image::getHighestPosition($id_product) + 1;
        $imageObj->cover = $cover;

        if ($imageObj->add()) {
            $img_folder_static = \Image::getImgFolderStatic($imageObj->id);
            $this->checkFolders($img_folder_static);
            $imagePath = _PS_PROD_IMG_DIR_ . $img_folder_static . $imageObj->id . '.jpg';
            $handler = fopen($imagePath, 'w');
            fwrite($handler, $content);
            fclose($handler);
            chmod($imagePath, 0775);

            $imagesTypes = \ImageType::getImagesTypes('products');
            foreach ($imagesTypes as $imageType) {
                \ImageManager::resize(
                    $imagePath,
                    _PS_PROD_IMG_DIR_ . \Image::getImgFolderStatic($imageObj->id) . $imageObj->id . '-' . stripslashes($imageType['name']) . '.jpg',
                    $imageType['width'],
                    $imageType['height']
                );
            }
        }
    }

    protected function checkFolders($folder)
    {
        if (!file_exists(_PS_PROD_IMG_DIR_ . $folder)) {
            mkdir(_PS_PROD_IMG_DIR_ . $folder, 0775, true);
        }
    }

    public function addToCategories($id_product, $categories = [])
    {
        $root = \Category::getRootCategory();
        $home = \Category::getHomeCategories($this->context->language->id);
        if (empty($categories) && $home) {
            $categories = (int) $home[0]['id_category'];
        } elseif ($categories && !is_array($categories)) {
            $categories = [(int) $categories];
        } elseif (!$categories) {
            $categories = [(int) $root->id];
        }

        $product = new \Product($id_product);

        return $product->addToCategories($categories);
    }

    abstract public function insertProductAttributes($id_product);

    abstract public function insertProductFeatures($id_product);

    abstract public function insertProductImages($id_product);
}
