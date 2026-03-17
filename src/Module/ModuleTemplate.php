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

namespace MpSoft\MpMassImportProducts\Module;

use MpSoft\MpMassImportProducts\Helpers\GetTwigEnvironment;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ModuleTemplate extends \Module
{
    const HOOK_ADMIN_SET_MEDIA = 'actionAdminControllerSetMedia';
    const HOOK_ACTION_ORDERS_LISTING_RESULT_MODIFIER = 'actionAdminOrdersListingResultsModifier';
    const HOOK_ACTION_ORDERS_LISTING_FIELDS_MODIFIER = 'actionAdminOrdersListingFieldsModifier';
    const HOOK_ACTION_PRODUCTS_LISTING_RESULT_MODIFIER = 'actionAdminProductsListingResultsModifier';
    const HOOK_ACTION_PRODUCTS_LISTING_FIELDS_MODIFIER = 'actionAdminProductsListingFieldsModifier';
    const HOOK_DISPLAY_BACK_OFFICE_HEADER = 'displayBackOfficeHeader';
    const HOOK_DISPLAY_BACK_OFFICE_FOOTER = 'displayBackOfficeFooter';
    const HOOK_DISPLAY_ON_HEADER = 'displayOnHeader';
    const TAB_ADMIN_CATALOG = 'adminCatalog';

    /**
     * Install a new menu
     *
     * @param string $tabLabel Tab label, if multilang [<id_lang> => <label>]
     * @param string $module_name Module name
     * @param string $parentTab Parent tab name
     * @param string $adminController Controller class name
     * @param string $icon Material Icons icon label
     * @param bool $active If true, Tab menu will be shown
     * @param bool $enabled If true Tab menu is enabled
     * @param string $wording_domain Wording domain
     * @param string $wording Wording type
     *
     * @return bool True if successful, False otherwise
     */
    public function installModuleTab(
        string|array $tabLabel,
        string $module_name,
        string $parentTab,
        string $adminController,
        string $icon = '',
        int $position = -1,
        string $route_name = '',
        bool $active = true,
        bool $enabled = true,
        string $wording_domain = '',
        string $wording = ''
    ) {
        // Create new admin tab
        $tab = new \Tab();

        if ($parentTab != -1) {
            $id_parent = $this->getIdTab($parentTab);

            // Get Parent Tab
            if (!$id_parent) {
                $id_parent = $this->getIdTab('AdminOtherModulesMp');
                if (!$id_parent) {
                    $parentTab = new \Tab();
                    $parentTab->class_name = 'AdminOtherModulesMp';
                    $parentTab->module = null;
                    $parentTab->id_parent = 0;
                    $parentTab->active = 1;
                    $parentTab->icon = 'extension';
                    foreach (\Language::getLanguages() as $language) {
                        $parentTab->name[$language['id_lang']] = $this->l('ALTRI MODULI');
                    }
                    $parentTab->add();

                    $id_parent = (int) $parentTab->id;
                }
            }

            $tab->id_parent = (int) $id_parent;
        } else {
            $tab->id_parent = -1;
        }

        if ($position == -1) {
            $position = \Tab::getNbTabs($id_parent);
        }

        $tab->name = [];

        if (!is_array($tabLabel)) {
            foreach (\Language::getLanguages(true) as $lang) {
                $tab->name[$lang['id_lang']] = $tabLabel;
            }
        } else {
            foreach ($tabLabel as $id_lang => $label) {
                $tab->name[$id_lang] = $label;
            }
        }

        $tab->class_name = $adminController;
        $tab->module = $module_name;
        $tab->position = $position;
        $tab->icon = $icon;
        $tab->route_name = $route_name;
        $tab->enabled = $enabled;
        $tab->active = $active;
        $tab->wording_domain = $wording_domain;
        $tab->wording = $wording;
        $result = $tab->add();

        return $result;
    }

    public function uninstallModuleTab($tab)
    {
        $idTab = $this->getIdTab($tab);
        $tab = new \Tab($idTab);

        if (\Validate::isLoadedObject($tab)) {
            $tab->delete();
        }

        return true;
    }

    private function getIdTab(string $parent)
    {
        $id_parent = SymfonyContainer::getInstance()
            ->get('prestashop.core.admin.tab.repository')
            ->findOneIdByClassName($parent);

        return (int) $id_parent;
    }

    public function renderTemplate($path, $params = [])
    {
        $twig = new GetTwigEnvironment($this->name);
        $twig->load("@ModuleTwig/{$path}");

        return $twig->render($params);
    }
}
