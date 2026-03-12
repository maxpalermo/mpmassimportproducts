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
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/vendor/autoload.php';;

use MpSoft\MpMassImportProducts\Module\ModuleTemplate;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class MpMassImportProducts extends ModuleTemplate implements WidgetInterface
{
    protected $adminClassName;

    public function __construct()
    {
        $this->name = 'mpmassimportproducts';
        $this->tab = 'administration';
        $this->version = '0.1.0';
        $this->author = 'Massimiliano Palermo';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->adminClassName = 'AdminMpMassImportProducts';
        $this->displayName = $this->l('MP Importazione massiva prodotti');
        $this->description = $this->l('Questo modulo importa i prodotti tramite i plugin a disposizione.');
        $this->confirmUninstall = $this->l('Sicuro di volere disinstallare questo modulo?');
        $this->ps_versions_compliancy = ['min' => '8.2', 'max' => '8.99'];
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        return "<h2>Widget rendered on {$hookName}</h2>";
    }

    public function renderWidget($hookName, array $configuration)
    {
        return $this->getWidgetVariables($hookName, $configuration);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        $hooks = [
            'actionAdminControllerSetMedia',
        ];

        return parent::install() &&
            $this->registerHook($hooks) &&
            $this->installModuleTab(
                $this->l('MP Importazione Massiva'),
                $this->name,
                '',
                'AdminMpMassImportProducts',
            );
    }

    public function hookActionAdminControllerSetMedia($params)
    {
        $controller = Tools::getValue('controller');
        if (Tools::strtolower($controller) === Tools::strtolower($this->adminClassName)) {
            $this->context->controller->addJqueryPlugin('growl');
        }
    }

    public function getContent()
    {
        // nothing
    }

    public function postProcess()
    {
        // nothing
    }
}
