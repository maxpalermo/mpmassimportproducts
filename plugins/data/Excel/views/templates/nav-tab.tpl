{extends file=$nav_tab_layout}

{block name='page_title'}

    <h3>{l s='Importa da file Excel' mod='mpmassimportproducts'}</h3>

{/block}

{block name='page_content'}

    <p>
        loremDolor fugiat esse ipsum est in non deserunt fugiat pariatur amet. Qui pariatur reprehenderit qui consequat cupidatat elit dolor dolor labore tempor proident mollit sit. Id sunt laborum nostrud elit id esse tempor esse velit mollit anim eiusmod. Sunt culpa velit id dolor non laboris mollit deserunt velit tempor. Enim excepteur ea et officia ex esse mollit et pariatur in duis. Enim aliquip quis ex laboris eiusmod.
    </p>

{/block}

{block name='page_footer'}
    <div class="btn-group d-flex justify-content-center" role="group" aria-label="Button group">
        <button type="button" class="btn btn-primary" onclick="randomInsert();">{l s='Random insert' mod='mpmassimportproducts'}</button>
        <button type="button" class="btn btn-secondary" onclick="randomCreateExcel();">{l s='Random create Excel' mod='mpmassimportproducts'}</button>
        <button type=" button" class="btn btn-success" onclick="loadTestExcel();">{l s='Load Test EXCEL' mod='mpmassimportproducts'}</button>
    </div>
{/block}

{block name='page_script'}
    <script>
        function randomInsert() {
            $.post(
                    "{Context::getContext()->link->getAdminLink('AdminMpMassImportProducts')}",
                    {
                        ajax: 1,
                        action: 'PluginCallback',
                        plugin: 'Excel',
                        callback_method: 'randomInsert',
                        params: []
                    }
                )
                .done(
                    function(response) {
                        alert('Done' + JSON.stringify(response));
                    })
                .fail(function(response) {
                    alert('Fail');
                });
        }

        function randomCreateExcel() {
            $.post(
                    "{Context::getContext()->link->getAdminLink('AdminMpMassImportProducts')}",
                    {
                        ajax: 1,
                        action: 'PluginCallback',
                        plugin: 'Excel',
                        callback_method: 'randomCreateExcel',
                        params: []
                    }
                )
                .done(
                    function(response) {
                        alert('Done' + JSON.stringify(response));
                    })
                .fail(
                    function(response) {
                        alert('Fail');
                    });
        }

        function loadTestExcel() {
            $.post(
                    "{Context::getContext()->link->getAdminLink('AdminMpMassImportProducts')}",
                    {
                        ajax: 1,
                        action: 'PluginCallback',
                        plugin: 'Excel',
                        callback_method: 'loadExcel',
                        params: []
                    }
                )
                .done(
                    function(response) {
                        alert('Done' + JSON.stringify(response));
                    })
                .fail(
                    function(response) {
                        alert('Fail');
                    });
        }
    </script>
{/block}