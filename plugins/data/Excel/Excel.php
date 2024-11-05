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

namespace MpSoft\MpMassImportProducts\Plugins\Excel;

use MpSoft\MpMassImportProducts\Test\TestInsertProduct;
use MpSoft\MpMassImportProducts\Xlsx\RandomProductsXlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Excel extends \MpSoft\MpMassImportProducts\Plugins\Plugin
{
    protected $file;
    protected $spreadsheet;
    protected $sheet;
    protected $rows;

    public function __construct($module)
    {
        $this->name = 'excel';
        $this->tab_parent = false;
        $this->tab = 'Excel';
        $this->icon = 'tab';
        $this->version = '1.0.0';
        $this->author = 'Massimiliano Palermo';
        $this->need_instance = 0;
        $this->module_key = '';
        $this->bootstrap = true;

        parent::__construct($module);

        $this->displayName = $this->module->l('Excel');
        $this->description = $this->module->l('Importa tramite Excel');
        $this->confirmUninstall = $this->module->l('Are you sure you want uninstall this module?');
        $this->ps_versions_compliancy = ['min' => '8.0', 'max' => _PS_VERSION_];
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        return "<h2>Widget rendered on {$hookName}</h2>";
    }

    public function renderWidget($hookName, array $configuration)
    {
        return "<h2>Widget rendered on {$hookName}</h2>";
    }

    public function sayHello()
    {
        return 'Hello';
    }

    public function randomInsert($times = 10)
    {
        $test = new TestInsertProduct();
        $ids = [];
        $errors = [];
        for ($i = 0; $i < $times; $i++) {
            $id = $test->insertRandomProduct();
            if (!$id) {
                $errors[] = $test->getError();
            } else {
                $ids[] = (int) $id;
            }
        }

        return [
            'ids' => $ids,
            'errors' => $errors,
        ];
    }

    public function randomCreateExcel()
    {
        $randomXls = new RandomProductsXlsx('template.xlsx', $this->module->getLocalPath() . 'xlsx/');
        $randomXls->randomInsert();
        $path = $randomXls->saveSheet('random_products.xlsx');

        return [
            'path' => $path,
        ];
    }

    public function loadExcel($file = '')
    {
        if (!$file) {
            $file = $this->module->getLocalPath() . 'xlsx/random_products.xlsx';
        }
        // Carica il file template.xlsx
        /** @var Spreadsheet */
        $spreadsheet = IOFactory::load($file);
        /** @var Worksheet */
        $sheet = $spreadsheet->getActiveSheet();

        $this->file = $file;
        $this->spreadsheet = $spreadsheet;
        $this->sheet = $sheet;

        $this->rows = $this->getSheetAsArray($sheet);
        $this->parseFeatures();

        return [
            'rows' => $this->rows,
        ];
    }

    protected function getSheetAsArray(Worksheet $sheet)
    {
        $rows = [];
        $i = 0;
        foreach ($sheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            $cells = [];
            foreach ($cellIterator as $cell) {
                $cells[] = $cell->getValue();
            }
            if ($i == 0) {
                $header = $cells;
            } else {
                $cells = array_combine($header, $cells);
                $rows[] = $cells;
            }
            $i++;
        }

        return $rows;
    }

    protected function parseFeatures()
    {
        $features = [];
        $products = [];
        foreach ($this->rows as $row) {
            $product = [];
            foreach ($row as $key => $column) {
                $matches = [];
                if (preg_match('/^id_product/', $key)) {
                    $id_product = (int) $column;
                    $product = [
                        'id_product' => $id_product,
                        'features' => [],
                    ];
                }
                if (preg_match('/^F:(.*)/', $key, $matches)) {
                    $feat_name = trim($matches[1]);
                    $feat_value = trim($column);
                    if (!$feat_name || !$feat_value) {
                        continue;
                    }
                    $id_feature = 0;
                    $id_feature_value = 0;

                    if (!isset($features[$feat_name])) {
                        $id_feature = (int) $this->getIdFeatureFromName($feat_name);
                        $id_feature_value = (int) $this->getIdFeatureValueFromValue($id_feature, $feat_value);

                        $features[$feat_name] = [
                            'id_feature' => $id_feature,
                            'name' => $feat_name,
                            'values' => [],
                        ];
                        if ($id_feature_value) {
                            $features[$feat_name]['values'][$id_feature_value][] = [
                                'id_feature_value' => $id_feature_value,
                                'value' => $feat_value,
                            ];
                            $product['features'][$id_feature][] = [
                                'id_feature_value' => $id_feature_value,
                                'value' => $feat_value,
                            ];
                        }
                    } else {
                        foreach ($features[$feat_name]['values'] as $values) {
                            foreach ($values as $value) {
                                if ($value['value'] == $feat_value) {
                                    $id_feature_value = (int) $value['id_feature_value'];

                                    break;
                                }
                            }
                        }
                        if (!$id_feature_value) {
                            $id_feature_value = (int) $this->getIdFeatureValueFromValue($features[$feat_name]['id_feature'], $feat_value);
                            $features[$feat_name]['values'][$id_feature_value][] = [
                                'id_feature_value' => $id_feature_value,
                                'value' => $feat_value,
                            ];
                        }
                        $product['features'][$id_feature][] = $id_feature_value;
                    }
                }
            }
            $products[] = $product;
        }
        $this->features = $features;

        return $products;
    }

    protected function getIdFeatureFromName($name)
    {
        $id_lang = (int) \Context::getContext()->language->id;
        $db = \Db::getInstance();
        $sql = 'SELECT id_feature FROM '
            . _DB_PREFIX_ . 'feature_lang WHERE name = "' . pSQL($name) . '" AND id_lang = ' . $id_lang;
        $id_feature = $db->getValue($sql);

        return (int) $id_feature;
    }

    protected function getIdFeatureValueFromValue($id_feature, $value)
    {
        $id_lang = (int) \Context::getContext()->language->id;
        $db = \Db::getInstance();
        $sql = new \DbQuery();
        $sql->select('a.id_feature_value')
            ->from('feature_value', 'a')
            ->leftJoin('feature_value_lang', 'b', 'a.id_feature_value = b.id_feature_value and b.id_lang = ' . $id_lang)
            ->where('a.id_feature = ' . (int) $id_feature)
            ->where('b.value = "' . pSQL($value) . '"');

        $id_feature_value = $db->getValue($sql);

        return (int) $id_feature_value;
    }
}
