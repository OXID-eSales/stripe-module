[{include file="headitem.tpl" title="GENERAL_ADMIN_TITLE"|oxmultilangassign box="box"}]

<form name="transfer" id="transfer" action="[{$oViewConf->getSelfLink()}]" method="post">
    [{$oViewConf->getHiddenSid()}]
    <input type="hidden" name="cl" value="admin_start">
    <input type="hidden" name="editlanguage" value="[{$editlanguage}]">
    <div class="stripe-connect-message messagebox">
        [{if $blIsSuccess}]
        <p class="success">[{oxmultilang ident="STRIPE_CONNECT_SUCCESS"}]</p>
        [{else}]
        <p class="error">[{oxmultilang ident="STRIPE_CONNECT_ERROR"}]</p>
        [{/if}]
    </div>

    <p><input type="submit" class="btn btn-primary" value="[{oxmultilang ident="STRIPE_BTN_TO_ADMIN"}]" /></p>
</form>

[{oxscript include="js/libs/jquery.min.js"}]
[{oxscript include="js/libs/jquery-ui.min.js"}]

[{include file="bottomnaviitem.tpl"}]
[{include file="bottomitem.tpl"}]
