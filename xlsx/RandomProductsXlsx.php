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

namespace MpSoft\MpMassImportProducts\Xlsx;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RandomProductsXlsx
{
    protected $file;
    protected $spreadsheet;
    protected $sheet;

    public function __construct($file, $path = '')
    {
        // Carica il file template.xlsx
        /** @var Spreadsheet */
        $spreadsheet = IOFactory::load($path . $file);
        /** @var Worksheet */
        $sheet = $spreadsheet->getActiveSheet();

        $this->file = $path . $file;
        $this->spreadsheet = $spreadsheet;
        $this->sheet = $sheet;
    }

    // Funzione per generare una stringa casuale
    public function randomString($length = 10)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    public function randomInsert($rows = 30)
    {
        /** @var Worksheet */
        $sheet = $this->sheet;
        /** @var array */
        $cells = $this->getFirstRowCells($this->spreadsheet);
        $header = $this->getHeaderSheet();

        // Genera dati casuali per N righe
        for ($row = 2; $row <= ($rows + 1); $row++) {
            // Partiamo dalla riga 2 per evitare di sovrascrivere l'intestazione
            foreach ($cells as $cell) {
                $key = $cell['key'];
                $size = isset($header[$key]['size']) ? (int) $header[$key]['size'] : 0;
                if (isset($header[$key])) {
                    switch ($header[$key]['type']) {
                        case 'int':
                            $sheet->setCellValue($cell['column'] . $row, rand(1, 100));

                            break;
                        case 'bool':
                            $sheet->setCellValue($cell['column'] . $row, rand(0, 1));

                            break;
                        case 'decimal':
                            $sheet->setCellValue($cell['column'] . $row, rand(1, 100) . '.' . rand(1, 99));

                            break;
                        case 'string':
                            $sheet->setCellValue($cell['column'] . $row, $this->randomString($size));

                            break;
                        case 'date':
                            $sheet->setCellValue($cell['column'] . $row, date('Y-m-d'));

                            break;
                        case 'datetime':
                            $sheet->setCellValue($cell['column'] . $row, date('Y-m-d H:i:s'));

                            break;
                        case 'text':
                            $sheet->setCellValue($cell['column'] . $row, $this->randomString($size));

                            break;
                    }
                }
            }
        }

        return $sheet;
    }

    public function saveSheet($name)
    {
        $path = dirname(__FILE__) . DIRECTORY_SEPARATOR;
        $r_path = str_replace(DIRECTORY_SEPARATOR, '\/', $path);
        $file = preg_replace('/^' . $r_path . '/', '', $name);
        $file = preg_replace('/\.xlsx$/', '', $file);
        $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . $file . '.xlsx';
        // Salva il file con i dati casuali
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($path);

        return $path;
    }

    /**
     * Legge la prima riga del foglio Excel e restituisce l'elenco delle celle.
     *
     * @param Spreadsheet $spreadsheet
     *
     * @return array
     */
    public function getFirstRowCells(Spreadsheet $spreadsheet)
    {
        $sheet = $spreadsheet->getActiveSheet();
        $highestColumn = $sheet->getHighestColumn();
        $iterator = $sheet->getColumnIterator('A', $highestColumn);
        $firstRowCells = [];

        // Itera attraverso tutte le celle della prima riga
        foreach ($iterator as $column) {
            $col_idx = $column->getColumnIndex() ;
            $cellValue = $sheet->getCell($col_idx . '1')->getValue();
            $firstRowCells[] = [
                'column' => $col_idx,
                'key' => $cellValue,
            ];
        }

        return $firstRowCells;
    }

    protected function getHeaderSheet()
    {
        return [
            'id_product' => ['type' => 'int', 'size' => 11, 'required' => true],
            'id_supplier' => ['type' => 'int', 'size' => 11, 'required' => false],
            'id_manufacturer' => ['type' => 'int', 'size' => 11, 'required' => false],
            'id_category_default' => ['type' => 'int', 'size' => 11, 'required' => true],
            'id_shop_default' => ['type' => 'int', 'size' => 11, 'required' => false],
            'id_tax_rules_group' => ['type' => 'int', 'size' => 11, 'required' => true],
            'on_sale' => ['type' => 'bool', 'size' => 1, 'required' => false],
            'online_only' => ['type' => 'bool', 'size' => 1, 'required' => false],
            'ean13' => ['type' => 'string', 'size' => 13, 'required' => false],
            'isbn' => ['type' => 'string', 'size' => 13, 'required' => false],
            'upc' => ['type' => 'string', 'size' => 12, 'required' => false],
            'mpn' => ['type' => 'string', 'size' => 40, 'required' => false],
            'ecotax' => ['type' => 'decimal', 'size' => 17, 'required' => false],
            'quantity' => ['type' => 'int', 'size' => 10, 'required' => true],
            'minimal_quantity' => ['type' => 'int', 'size' => 10, 'required' => true],
            'price' => ['type' => 'decimal', 'size' => 20, 'required' => true],
            'wholesale_price' => ['type' => 'decimal', 'size' => 20, 'required' => false],
            'unity' => ['type' => 'string', 'size' => 255, 'required' => false],
            'unit_price_ratio' => ['type' => 'decimal', 'size' => 20, 'required' => false],
            'additional_shipping_cost' => ['type' => 'decimal', 'size' => 20, 'required' => false],
            'reference' => ['type' => 'string', 'size' => 32, 'required' => false],
            'supplier_reference' => ['type' => 'string', 'size' => 32, 'required' => false],
            'location' => ['type' => 'string', 'size' => 64, 'required' => false],
            'width' => ['type' => 'decimal', 'size' => 20, 'required' => false],
            'height' => ['type' => 'decimal', 'size' => 20, 'required' => false],
            'depth' => ['type' => 'decimal', 'size' => 20, 'required' => false],
            'weight' => ['type' => 'decimal', 'size' => 20, 'required' => false],
            'out_of_stock' => ['type' => 'int', 'size' => 10, 'required' => false],
            'quantity_discount' => ['type' => 'bool', 'size' => 1, 'required' => false],
            'customizable' => ['type' => 'int', 'size' => 10, 'required' => false],
            'uploadable_files' => ['type' => 'int', 'size' => 10, 'required' => false],
            'text_fields' => ['type' => 'int', 'size' => 10, 'required' => false],
            'active' => ['type' => 'bool', 'size' => 1, 'required' => true],
            'redirect_type' => ['type' => 'string', 'size' => 255, 'required' => false],
            'available_for_order' => ['type' => 'bool', 'size' => 1, 'required' => false],
            'available_date' => ['type' => 'date', 'size' => null, 'required' => false],
            'condition' => ['type' => 'string', 'size' => 255, 'required' => false],
            'show_price' => ['type' => 'bool', 'size' => 1, 'required' => false],
            'indexed' => ['type' => 'bool', 'size' => 1, 'required' => false],
            'visibility' => ['type' => 'string', 'size' => 255, 'required' => false],
            'cache_default_attribute' => ['type' => 'int', 'size' => 10, 'required' => false],
            'advanced_stock_management' => ['type' => 'bool', 'size' => 1, 'required' => false],
            'date_add' => ['type' => 'datetime', 'size' => null, 'required' => true],
            'date_upd' => ['type' => 'datetime', 'size' => null, 'required' => true],
            'pack_stock_type' => ['type' => 'int', 'size' => 10, 'required' => false],
            'state' => ['type' => 'int', 'size' => 10, 'required' => false],
            'additional_delivery_times' => ['type' => 'bool', 'size' => 1, 'required' => false],
            'id_shop_list' => ['type' => 'string', 'size' => 255, 'required' => false],
            'description' => ['type' => 'text', 'size' => null, 'required' => false],
            'description_short' => ['type' => 'text', 'size' => null, 'required' => false],
            'link_rewrite' => ['type' => 'string', 'size' => 255, 'required' => true],
            'meta_description' => ['type' => 'string', 'size' => 255, 'required' => false],
            'meta_keywords' => ['type' => 'string', 'size' => 255, 'required' => false],
            'meta_title' => ['type' => 'string', 'size' => 255, 'required' => false],
            'name' => ['type' => 'string', 'size' => 255, 'required' => true],
            'available_now' => ['type' => 'string', 'size' => 255, 'required' => false],
            'available_later' => ['type' => 'string', 'size' => 255, 'required' => false],
            'delivery_in_stock' => ['type' => 'string', 'size' => 255, 'required' => false],
            'delivery_out_stock' => ['type' => 'string', 'size' => 255, 'required' => false],
        ];
    }
}
