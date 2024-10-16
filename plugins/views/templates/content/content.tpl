<style>
    .panel-title {
        display: block;
        padding: 0;
        margin: 0;
        width: 100%;
    }
</style>

<div class="panel mt-4">
    <div class="panel-heading">
        <div class="panel-title">
            {block name='page_title'}
                <!-- Title -->
            {/block}
        </div>
    </div>
    <div class="panel-body">
        {block name='page_content'}
            <!-- Page content -->
        {/block}
    </div>
    <div class="panel-footer">
        {block name='page_footer'}
            <!-- Footer content -->
        {/block}
    </div>
</div>

{block name='page_script'}
    <!-- Page script -->
{/block}