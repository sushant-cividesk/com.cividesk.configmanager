{if $criticalCss}
<style id="civicfg-critical-css" type="text/css">
{$criticalCss}
</style>
{/if}
<div class="crm-block crm-content-block crm-configmanager-block">
  {include file="CRM/Configmanager/Page/Partials/Header.tpl"}
  {include file="CRM/Configmanager/Page/Partials/Filter.tpl"}
  {include file="CRM/Configmanager/Page/Partials/Sync.tpl"}
  {include file="CRM/Configmanager/Page/Partials/Import.tpl"}
  {include file="CRM/Configmanager/Page/Partials/Export.tpl"}
  {include file="CRM/Configmanager/Page/Partials/Settings.tpl"}
  {include file="CRM/Configmanager/Page/Partials/Modals.tpl"}
</div>
