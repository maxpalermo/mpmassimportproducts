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

class RedirectType
{
    public const NONE = '';
    public const NOT_FOUND = '404';
    public const GONE = '410';
    public const MOVED_PERMANENTLY_PRODUCT = '301-product';
    public const FOUND_PRODUCT = '302-product';
    public const MOVED_PERMANENTLY_CATEGORY = '301-category';
    public const FOUND_CATEGORY = '302-category';
    public const OK_DISPLAYED = '200-displayed';
    public const NOT_FOUND_DISPLAYED = '404-displayed';
    public const GONE_DISPLAYED = '410-displayed';
    public const DEFAULT = 'default';

    public static function getValues(): array
    {
        return [
            self::NONE,
            self::NOT_FOUND,
            self::GONE,
            self::MOVED_PERMANENTLY_PRODUCT,
            self::FOUND_PRODUCT,
            self::MOVED_PERMANENTLY_CATEGORY,
            self::FOUND_CATEGORY,
            self::OK_DISPLAYED,
            self::NOT_FOUND_DISPLAYED,
            self::GONE_DISPLAYED,
            self::DEFAULT,
        ];
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::getValues(), true);
    }

    public static function getDefault(): string
    {
        return self::NONE;
    }

    public static function getLabel(string $type): string
    {
        switch ($type) {
            case self::NONE:
                return 'None';
            case self::NOT_FOUND:
                return 'Not Found';
            case self::GONE:
                return 'Gone';
            case self::MOVED_PERMANENTLY_PRODUCT:
                return 'Moved Permanently Product';
            case self::FOUND_PRODUCT:
                return 'Found Product';
            case self::MOVED_PERMANENTLY_CATEGORY:
                return 'Moved Permanently Category';
            case self::FOUND_CATEGORY:
                return 'Found Category';
            case self::OK_DISPLAYED:
                return 'Ok Displayed';
            case self::NOT_FOUND_DISPLAYED:
                return 'Not Found Displayed';
            case self::GONE_DISPLAYED:
                return 'Gone Displayed';
            case self::DEFAULT:
                return 'Default';
            default:
                return '';
        }
    }
}
