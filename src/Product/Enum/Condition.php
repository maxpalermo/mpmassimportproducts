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
class Condition
{
    public const NEW = 'new';
    public const USED = 'used';
    public const REFURBISHED = 'refurbished';

    public static function getValues(): array
    {
        return [
            self::NEW,
            self::USED,
            self::REFURBISHED,
        ];
    }

    public static function isValid(string $condition): bool
    {
        return in_array($condition, self::getValues(), true);
    }

    public static function getDefault(): string
    {
        return self::NEW;
    }

    public static function getLabel(string $condition): string
    {
        switch ($condition) {
            case self::NEW:
                return 'New';
            case self::USED:
                return 'Used';
            case self::REFURBISHED:
                return 'Refurbished';
            default:
                return '';
        }
    }
}
