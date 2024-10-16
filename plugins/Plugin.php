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

namespace MpSoft\MpMassImportProducts\Plugins;

class Plugin
{
    protected $context;
    protected $translator;
    protected $plugin_folder = __DIR__ . '/data';
    protected $plugins = [];
    /** @var \Module */
    protected $module;

    protected $name;
    protected $tab_parent;
    protected $tab;
    protected $icon;
    protected $version;
    protected $author;
    protected $need_instance;
    protected $module_key;
    protected $bootstrap;
    protected $displayName;
    protected $description;
    protected $confirmUninstall;
    protected $ps_versions_compliancy;
    protected $content;
    protected $active;

    protected $nav_tab_layout;

    public function __construct($module)
    {
        $this->module = $module;
        $this->active = true;
        $this->nav_tab_layout = $this->module->getLocalPath() . 'plugins/views/templates/content/content.tpl';
        $this->context = \Context::getContext();
        $this->translator = $this->context->getTranslator();
    }

    public function getLocalPath()
    {
        return $this->plugin_folder . DIRECTORY_SEPARATOR . \Tools::ucfirst($this->name) . DIRECTORY_SEPARATOR;
    }

    public function fetchPlugins()
    {
        $directories = glob($this->plugin_folder . '/*', GLOB_ONLYDIR);

        foreach ($directories as $directory) {
            $pluginName = basename($directory);
            $pluginFile = $directory . '/' . $pluginName . '.php';

            if (file_exists($pluginFile)) {
                $plugin = $this->loadPlugin($pluginName);
                if ($plugin->active) {
                    $this->plugins[] = $pluginName;
                }
            }
        }

        return $this->plugins;
    }

    public function getPlugins()
    {
        return $this->plugins;
    }

    public function staticLoadPlugin($pluginName, $module)
    {
        $pluginClass = new self($module);

        return $pluginClass->loadPlugin($pluginName);
    }

    public function loadPlugin($pluginName): Plugin
    {
        $pluginFile = $this->plugin_folder . '/' . $pluginName . '/' . $pluginName . '.php';

        if (file_exists($pluginFile)) {
            require_once $pluginFile;
            $pluginClass = 'MpSoft\MpMassImportProducts\Plugins\\' . $pluginName . '\\' . $pluginName;

            return new $pluginClass($this->module);
        }

        throw new \Exception(sprintf('Plugin %s not found', $pluginName));
    }

    public function displayPluginContent($pluginName)
    {
        $plugin = $this->loadPlugin($pluginName);

        if ($plugin) {
            return $plugin->getContent();
        }

        return false;
    }

    public function getPlugin($pluginName)
    {
        return $this->loadPlugin($pluginName);
    }

    public function getPluginList()
    {
        return $this->plugins;
    }

    public function getPluginFolder()
    {
        return $this->plugin_folder;
    }

    public function getModule()
    {
        return $this->module;
    }

    public function getPluginName($plugin)
    {
        return $plugin->name;
    }

    public function getPluginTab($plugin)
    {
        return $plugin->tab;
    }

    public function getPluginVersion($plugin)
    {
        return $plugin->version;
    }

    public function getPluginAuthor($plugin)
    {
        return $plugin->author;
    }

    public function getPluginNeedInstance($plugin)
    {
        return $plugin->need_instance;
    }

    public function getPluginModuleKey($plugin)
    {
        return $plugin->module_key;
    }

    public function getPluginBootstrap($plugin)
    {
        return $plugin->bootstrap;
    }

    public function getPluginDisplayName($plugin)
    {
        return $plugin->displayName;
    }

    public function getPluginDescription($plugin)
    {
        return $plugin->description;
    }

    public function getPluginConfirmUninstall($plugin)
    {
        return $plugin->confirmUninstall;
    }

    public function getPluginPsVersionsCompliancy($plugin)
    {
        return $plugin->ps_versions_compliancy;
    }

    public function getPluginWidgetVariables($plugin, $hookName, $configuration)
    {
        return $plugin->getWidgetVariables($hookName, $configuration);
    }

    public function getPluginRenderWidget($plugin, $hookName, $configuration)
    {
        return $plugin->renderWidget($hookName, $configuration);
    }

    public function getPluginListWidgetVariables($hookName, $configuration)
    {
        $pluginList = $this->getPluginList();
        $widgetVariables = [];

        foreach ($pluginList as $pluginName) {
            $plugin = $this->loadPlugin($pluginName);
            $widgetVariables[$pluginName] = $this->getPluginWidgetVariables($plugin, $hookName, $configuration);
        }

        return $widgetVariables;
    }

    public function getPluginListRenderWidget($hookName, $configuration)
    {
        $pluginList = $this->getPluginList();
        $renderWidget = [];

        foreach ($pluginList as $pluginName) {
            $plugin = $this->loadPlugin($pluginName);
            $renderWidget[$pluginName] = $this->getPluginRenderWidget($plugin, $hookName, $configuration);
        }

        return $renderWidget;
    }

    public function getPluginListWidget($hookName, $configuration)
    {
        $widget = $this->getPluginListRenderWidget($hookName, $configuration);
        $widgetVariables = $this->getPluginListWidgetVariables($hookName, $configuration);

        return [
            'widget' => $widget,
            'widgetVariables' => $widgetVariables,
        ];
    }

    public function getPluginListWidgetByHook($hookName, $configuration)
    {
        $widget = $this->getPluginListRenderWidget($hookName, $configuration);
        $widgetVariables = $this->getPluginListWidgetVariables($hookName, $configuration);

        return [
            'widget' => $widget,
            'widgetVariables' => $widgetVariables,
        ];
    }

    public function renderPluginMenu()
    {
        $pluginList = $this->getPluginList();
        $menu = [];

        foreach ($pluginList as $pluginName) {
            $plugin = $this->loadPlugin($pluginName);
            $menu[] = [
                'name' => $this->getPluginDisplayName($plugin),
                'href' => \Context::getContext()->link->getAdminLink('AdminMpMassImportProducts') . '&configure=' . $pluginName,
                'icon' => $plugin->icon,
            ];
        }

        $tpl = \Context::getContext()->smarty->createTemplate('module:mpmassimportproducts/views/templates/admin/plugins/top_menu.tpl');
        $tpl->assign('plugins', $menu);

        return $tpl->fetch();
    }

    public function callBack($method, $params = [])
    {
        if (method_exists($this, $method)) {
            if (!$params) {
                $params = [];
            }

            return call_user_func_array([$this, $method], $params);
        }

        throw new \Exception(sprintf('Method %s not found', $method));
    }

    public function getContent($params = null)
    {
        $tpl_path = $this->getLocalPath() . 'views/templates/nav-tab.tpl';
        $tpl = \Context::getContext()->smarty->createTemplate($tpl_path, null, null, \Context::getContext()->smarty);
        $tpl->assign([
            'nav_tab_layout' => $this->nav_tab_layout,
            'name' => $this->name,
            'icon' => $this->icon,
            'description' => $this->description,
        ]);

        return $tpl->fetch();
    }
}
