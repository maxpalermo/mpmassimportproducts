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

use MpSoft\MpMassImportProducts\Models\ModelMassImportData;
use MpSoft\MpMassImportProducts\Module\ModuleTemplate;
use MpSoft\MpMassImportProducts\Plugins\Plugin;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class MpMassImportProducts extends ModuleTemplate implements WidgetInterface
{
    public $active_panel;
    protected $mass_import_data;
    protected $plugin_list = [];
    protected $pluginClass;
    protected $adminClassName;

    public function __construct()
    {
        $this->name = 'mpmassimportproducts';
        $this->tab = 'administration';
        $this->version = '0.1.0';
        $this->author = 'Massimiliano Palermo';
        $this->need_instance = 0;
        $this->module_key = '';
        $this->bootstrap = true;

        parent::__construct();

        $this->adminClassName = 'AdminMpMassImportProducts';
        $this->displayName = $this->l('MP Importazione massiva prodotti');
        $this->description = $this->l('Questo modulo importa i prodotti tramite i plugin a disposizione.');
        $this->confirmUninstall = $this->l('Are you sure you want uninstall this module?');
        $this->ps_versions_compliancy = ['min' => '8.0', 'max' => _PS_VERSION_];
        $this->mass_import_data = new ModelMassImportData();
        $pluginClass = new MpSoft\MpMassImportProducts\Plugins\Plugin($this);
        $pluginClass->fetchPlugins();
        $this->plugin_list = $pluginClass->getPlugins();
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
            'actionFrontControllerSetMedia',
            'displayPluginContent',
        ];

        return parent::install()
            && $this->registerHook($hooks)
            && $this->installModuleTab(
                $this->l('MP Importazione Massiva'),
                $this->name,
                'AdminCatalog',
                'AdminMpMassImportProducts',
                'fa-download',
            );
    }

    public function hookActionAdminControllerSetMedia($params)
    {
        $controller = Tools::getValue('controller');
        if (Tools::strtolower($controller) === Tools::strtolower($this->adminClassName)) {
            $this->context->controller->addCSS($this->_path . 'views/css/bootstrap.min.css');
            $this->context->controller->addJS($this->_path . 'views/js/bootstrap.bundle.min.js');
            $this->context->controller->addJqueryPlugin('growl');
        }
    }

    public function hookActionFrontControllerSetMedia($params)
    {
        // nothing
    }

    public function hookDisplayPluginContent($params)
    {
        $plugin_name = $params['plugin_name'];
        $this->pluginClass = new Plugin($this);
        $plugin = $this->pluginClass->loadPlugin($plugin_name);

        return $plugin->getContent();
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
