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

namespace MpSoft\MpMassImportProducts\Test;
use MpSoft\MpMassImportProducts\Product\AbstractProduct;

class TestInsertProduct extends AbstractProduct
{
    protected $context;

    public function __construct($id = null, $id_lang = null, $id_shop = null)
    {
        parent::__construct($id, $id_lang, $id_shop);
        $this->context = \Context::getContext();
    }

    /**
     * Insert a random product in the database.
     *
     * @return int The ID of the inserted product.
     */
    public function insertRandomProduct()
    {
        $unity = ['mt', 'kg', 'lt', 'pz', 'mm', 'cm', 'gr', 'ml', 'cl', 'lt', 'kg', 'g', 'mg', 'm', 'cm', 'mm', 'km', 'm2', 'm3'];

        $this->id_product = rand(11111, 99999);
        $this->id_supplier = rand(1, 10);
        $this->id_manufacturer = rand(1, 10);
        $this->id_category_default = rand(1, 10);
        $this->id_shop_default = 1;
        $this->id_tax_rules_group = 1;
        $this->on_sale = 0;
        $this->online_only = 0;
        $this->ean13 = rand(1111111111111, 9999999999999);
        $this->isbn = rand(1111111111, 9999999999);
        $this->upc = rand(1111111111, 9999999999);
        $this->mpn = rand(1111111111, 9999999999);
        $this->ecotax = 0;
        $this->quantity = rand(1, 100);
        $this->minimal_quantity = 1;
        $this->price = rand(1, 100);
        $this->wholesale_price = rand(1, 100);
        $this->unity = $unity[rand(0, count($unity) - 1)];
        $this->unit_price_ratio = 0;
        $this->additional_shipping_cost = 0;
        $this->reference = 'REF' . rand(11111, 99999);
        $this->supplier_reference = 'SUP' . rand(11111, 99999);
        $this->location = 'LOC' . rand(11111, 99999);
        $this->width = rand(1, 100);
        $this->height = rand(1, 100);
        $this->depth = rand(1, 100);
        $this->weight = rand(1, 100);
        $this->out_of_stock = 2;
        $this->quantity_discount = 0;
        $this->customizable = 0;
        $this->uploadable_files = 0;
        $this->text_fields = 0;
        $this->active = 1;
        $this->redirect_type = $this->getDefaultRedirectType();
        $this->available_for_order = 1;
        $this->available_date = date('Y-m-d H:i:s');
        $this->condition = $this->getDefaultCondition();
        $this->show_price = 1;
        $this->indexed = 1;
        $this->visibility = $this->getDefaultVisibility();
        $this->cache_default_attribute = 0;
        $this->advanced_stock_management = 0;
        $this->date_add = date('Y-m-d H:i:s');
        $this->date_upd = date('Y-m-d H:i:s');
        $this->pack_stock_type = 3;
        $this->state = 1;
        $this->additional_delivery_times = 0;
        $this->id_shop_list = [1];
        $this->description = 'Description of product ' . $this->id_product;
        $this->description_short = 'Short description of product ' . $this->id_product;
        $this->link_rewrite = '';
        $this->meta_description = 'Meta description of product ' . $this->id_product;
        $this->meta_keywords = 'Meta keywords of product ' . $this->id_product;
        $this->meta_title = 'Meta title of product ' . $this->id_product;
        $this->name = 'Product ' . $this->id_product;
        $this->available_now = 'Available now';
        $this->available_later = 'Available later';
        $this->delivery_in_stock = 'Delivery in stock';
        $this->delivery_out_stock = 'Delivery out of stock';

        $id_product = $this->insert(true);
        if ($id_product) {
            $this->insertProductCategories($id_product, [$this->id_category_default]);
            $this->insertProductAttributes($id_product);
            $this->insertProductFeatures($id_product);
            $this->insertProductImages($id_product);
        }

        return $id_product;
    }

    /**
     * {@inheritdoc}
     */
    public function import()
    {
        $this->insertRandomProduct();
    }

    public function insertProductCategories($id_product)
    {
        $tot_categories = \Category::getCategories($this->context->language->id, true, false);
        $times = rand(1, 10);
        $categories = [];
        for ($i = 0; $i < $times; $i++) {
            $count_categories = count($tot_categories);
            $idx = rand(0, $count_categories - 1);
            $id_category = $tot_categories[$idx]['id_category'];
            $categories[$id_category] = $id_category;
        }

        return $this->addToCategories($id_product, $categories);
    }

    public function insertProductAttributes($id_product)
    {
        return true;
    }

    public function insertProductFeatures($id_product)
    {
        return true;
    }

    public function insertProductImages($id_product)
    {
        $times = rand(1, 5);
        for ($i = 0; $i < $times; $i++) {
            $image_link = 'https://picsum.photos/800/600';
            $content = $this->getImageFromLink($image_link);
            $this->insertImage($id_product, $content, !$i);
        }
    }
}

$properties = [
    'id_product' => '',
    'id_supplier' => '',
    'id_manufacturer' => '',
    'id_category_default' => '',
    'id_shop_default' => '',
    'id_tax_rules_group' => '',
    'on_sale' => '',
    'online_only' => '',
    'ean13' => '',
    'isbn' => '',
    'upc' => '',
    'mpn' => '',
    'ecotax' => '',
    'quantity' => '',
    'minimal_quantity' => '',
    'price' => '',
    'wholesale_price' => '',
    'unity' => '',
    'unit_price_ratio' => '',
    'additional_shipping_cost' => '',
    'reference' => '',
    'supplier_reference' => '',
    'location' => '',
    'width' => '',
    'height' => '',
    'depth' => '',
    'weight' => '',
    'out_of_stock' => '',
    'quantity_discount' => '',
    'customizable' => '',
    'uploadable_files' => '',
    'text_fields' => '',
    'active' => '',
    'redirect_type' => '',
    'available_for_order' => '',
    'available_date' => '',
    'condition' => '',
    'show_price' => '',
    'indexed' => '',
    'visibility' => '',
    'cache_default_attribute' => '',
    'advanced_stock_management' => '',
    'date_add' => '',
    'date_upd' => '',
    'pack_stock_type' => '',
    'state' => '',
    'additional_delivery_times' => '',
    'id_shop_list' => '',
    'description' => '',
    'description_short' => '',
    'link_rewrite' => '',
    'meta_description' => '',
    'meta_keywords' => '',
    'meta_title' => '',
    'name' => '',
    'available_now' => '',
    'available_later' => '',
    'delivery_in_stock' => '',
    'delivery_out_stock' => '',
];