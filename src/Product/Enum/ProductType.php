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

namespace MpSoft\MpMassImportProducts\Product\Enum;

class ProductType
{
    public const STANDARD = 'standard';
    public const PACK = 'pack';
    public const VIRTUAL = 'virtual';
    public const COMBINATIONS = 'combinations';

    public static function getValues(): array
    {
        return [
            self::STANDARD,
            self::PACK,
            self::VIRTUAL,
            self::COMBINATIONS,
        ];
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::getValues(), true);
    }

    public static function getDefault(): string
    {
        return self::STANDARD;
    }

    public static function getLabel(string $type): string
    {
        switch ($type) {
            case self::STANDARD:
                return 'Standard';
            case self::PACK:
                return 'Pack';
            case self::VIRTUAL:
                return 'Virtual';
            case self::COMBINATIONS:
                return 'Combinations';
            default:
                return '';
        }
    }
}
