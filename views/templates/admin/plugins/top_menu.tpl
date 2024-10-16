{**
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
 *}

<style>
    li.nav-item a.nav-link.active {
        background-color: #3a8eaf !important;
        color: #fff !important;
    }
</style>

<ul class="nav nav-pills" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="home-tab" data-toggle="pill" href="#tab-content-home" role="tab" aria-controls="tab-content-home" aria-expanded="true">
            <span class="material-icons mr-2">home</span>
            <span>{l s='Home' mod='mpmassimportproducts'}</span>
        </a>
    </li>
    {foreach from=$plugins item=plugin}
        <li class="nav-item">
            <a class="nav-link" id="{$plugin.name}-tab" data-toggle="pill" href="#tab-content-{$plugin.name}" role="tab" aria-controls="pills-home" aria-expanded="true">{if $plugin.icon}<span class="material-icons mr-2">{$plugin.icon}</span>{/if} <span>{$plugin.name}</span></a>
        </li>
    {/foreach}
</ul>


<div class="tab-content" id="pills-Plugin">
    <div class="tab-pane fade active in" id="tab-content-home" role="tabpanel" aria-labelledby="home-tab">
        <div class="panel mt-4">
            <div class="panel-heading">
                Header
            </div>
            <div class="panel-body">
                <h5 class="panel-title">Title</h5>
                <p class="panel-text">Content</p>
            </div>
            <div class="panel-footer">
                Footer
            </div>
        </div>
    </div>
    {foreach from=$plugins item=plugin}
        <div class="tab-pane fade" id="tab-content-{$plugin.name}" role="tabpanel" aria-labelledby="{$plugin.name}-tab">
            {hook::exec('displayPluginContent', ['plugin_name' => $plugin.name])}
        </div>
    {/foreach}
</div>