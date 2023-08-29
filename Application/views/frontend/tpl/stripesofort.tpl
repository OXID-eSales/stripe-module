[{assign var="aStripeSofortCountries" value=$oView->stripeGetSofortCountries()}]
<div id="stripe_sofort_error_box" class="form-group" style="display:none;">
    <div class="col-lg-3"></div>
    <div class="col-lg-9">
        <div class="form-control" style="background-color:#ff5959" id="stripe_sofort_error">[{oxmultilang ident="ERROR"}]</div>
    </div>
</div>

<div class="form-group">
    <label for="stripe_sofort_country" class="req control-label col-lg-3">[{oxmultilang ident="STRIPE_SOFORT_COUNTRY"}]</label>
    <div class="col-lg-9">
        <select id="stripe_sofort_country" name="dynvalue[stripe_sofort_country]" required="required">
            <option value="">[{oxmultilang ident="PLEASE_CHOOSE"}]</option>
            [{foreach from=$aStripeSofortCountries item=countryCode}]
            <option value="[{$countryCode}]" [{if $dynvalue.stripe_sofort_country == $countryCode}]selected="selected"[{/if}]>[{oxmultilang ident="STRIPE_COUNTRY_"|cat:$countryCode|oxupper}]</option>
            [{/foreach}]
        </select>
    </div>
</div>
[{oxstyle include=$oViewConf->getModuleUrl('stripe','out/src/css/stripe.css')}]
