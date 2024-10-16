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

namespace MpSoft\MpMassImportProducts\Plugins\Isacco;

class Isacco extends \MpSoft\MpMassImportProducts\Plugins\Plugin
{
    public function __construct($module)
    {
        $this->name = 'isacco';
        $this->tab_parent = false;
        $this->tab = 'Isacco';
        $this->icon = 'shopping_cart_checkout';
        $this->version = '1.0.0';
        $this->author = 'Massimiliano Palermo';
        $this->need_instance = 0;
        $this->module_key = '';
        $this->bootstrap = true;

        parent::__construct($module);

        $this->displayName = $this->module->l('Isacco');
        $this->description = $this->module->l('Importa tramite API di Isacco');
        $this->confirmUninstall = $this->module->l('Are you sure you want uninstall this module?');
        $this->ps_versions_compliancy = ['min' => '8.0', 'max' => _PS_VERSION_];
        $this->active = true;
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        return "<h2>Widget rendered on {$hookName}</h2>";
    }

    public function renderWidget($hookName, array $configuration)
    {
        return "<h2>Widget rendered on {$hookName}</h2>";
    }

    public function callback1($params = null)
    {
        return
            [
                'success' => true,
                'message' => 'Callback 1 executed',
            ];
    }
}
