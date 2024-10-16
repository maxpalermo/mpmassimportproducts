{extends file=$nav_tab_layout}

{block name='page_title'}
    <h3>
        <span class="material-icons mr-1">cloud_upload</span>
        <span>{l s='Importa tramite API Isacco' mod='mpmassimportproducts'}</span>
    </h3>
{/block}

{block name='page_content'}

    <p>
        Lorem enim irure eu laborum enim dolore ex velit. Dolore exercitation ex magna ullamco tempor laboris ea et duis magna. Fugiat occaecat non minim adipisicing. Lorem consectetur consectetur culpa ullamco consectetur culpa id duis minim.

        Esse in nostrud reprehenderit mollit aliqua nulla culpa velit culpa eu do. Labore sit quis eiusmod magna dolor exercitation non ipsum excepteur sint fugiat officia enim. Non fugiat duis aute in fugiat laborum irure et velit duis dolore anim sunt.

        Enim id nulla incididunt dolore est eiusmod officia veniam fugiat id ipsum duis incididunt culpa. Veniam anim nulla eiusmod officia velit. Nisi irure magna elit adipisicing amet Lorem cupidatat laboris est fugiat sunt. Aute pariatur sint in et veniam non deserunt.

        Eu non ex esse incididunt excepteur voluptate nostrud incididunt mollit irure officia tempor aliqua. Ullamco ex et anim aliqua cupidatat magna in. Eu ad eiusmod aute labore non cupidatat velit magna elit ut.
    </p>

{/block}

{block name='page_footer'}
    <div class="btn-group d-flex justify-content-center" role="group" aria-label="Button group">
        <button type="button" class="btn btn-primary" onclick="callback1();">{l s='Button 1' mod='mpmassimportproducts'}</button>
        <button type="button" class="btn btn-secondary">{l s='Button 2' mod='mpmassimportproducts'}</button>
        <button type="button" class="btn btn-success">{l s='Button 3' mod='mpmassimportproducts'}</button>
    </div>
{/block}

{block name='page_script'}
    <script>
        function callback1() {
            $.post(
                    "{Context::getContext()->link->getAdminLink('AdminMpMassImportProducts')}",
                    {
                        ajax: 1,
                        action: 'PluginCallback',
                        plugin: 'Isacco',
                        callback_method: 'callback1',
                        params: {
                            param1: 'value1',
                            param2: 'value2',
                        }
                    }
                )
                .done(function(response) {
                    alert('Done' + JSON.stringify(response));
                })
                .fail(function(response) {
                    alert('Fail');
                });
        }
    </script>
{/block}