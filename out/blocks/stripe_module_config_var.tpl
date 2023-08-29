[{if $module_var == 'sStripeStatusPending' || $module_var == 'sStripeStatusProcessing' || $module_var == 'sStripeStatusCancelled'}]
    <dl>
        <dt>
            <select class="select" name="confselects[[{$module_var}]]" [{ $readonly }]>
                [{foreach from=$oView->stripeGetOrderFolders() key=sFolder item=sColor}]
                    <option value="[{$sFolder}]" [{if $confselects.$module_var == $sFolder}]selected[{/if}]>[{ oxmultilang ident=$sFolder noerror=true }]</option>
                [{/foreach}]
            </select>
            [{oxinputhelp ident="HELP_SHOP_MODULE_`$module_var`"}]
        </dt>
        <dd>
            [{oxmultilang ident="SHOP_MODULE_`$module_var`"}]
        </dd>
    </dl>
[{elseif $module_var == 'sStripeTestToken' || $module_var == 'sStripeLiveToken' || $module_var == 'sStripeTestPk' || $module_var == 'sStripeLivePk'}]
    [{if $module_var == 'sStripeTestToken' || $module_var == 'sStripeLiveToken'}]
    <dl>
        <dt>
            <div>
                <input type="password" readonly disabled class="txt" style="width: 250px;" name="confstrs[[{$module_var}]]" value="[{$confstrs.$module_var}]" [{$readonly}]>
                [{oxinputhelp ident="HELP_SHOP_MODULE_`$module_var`"}]
                <br/>
                [{if $confstrs.$module_var}]
                    [{if $oView->stripeIsApiKeyUsable($module_var)}]
                    <span id="[{$module_var}]_status" style="color: green">[{oxmultilang ident="STRIPE_APIKEY_CONNECTED"}]</span>
                    [{else}]
                    <span id="[{$module_var}]_status" style="color: crimson">[{oxmultilang ident="STRIPE_APIKEY_DISCONNECTED"}]</span>
                    [{/if}]
                [{/if}]
            </div>
        </dt>
        <dd style="white-space: nowrap;">
            <span style="float:left;">[{oxmultilang ident="SHOP_MODULE_`$module_var`"}]</span>
            <span><a href="[{$oView->stripeGetConnectUrl($module_var)}]" class="stripe-connect" target="_blank" rel="noreferrer noopener"><span>Connect with</span></a></span>
        </dd>
        <div class="spacer"></div>
    </dl>
    [{else}]
        <dl>
            <dt>
                <input type="text" readonly disabled class="txt" style="width: 250px;" name="confstrs[[{$module_var}]]" value="[{$confstrs.$module_var}]" [{$readonly}]>
                [{oxinputhelp ident="HELP_SHOP_MODULE_`$module_var`"}]
            </dt>
            <dd style="white-space: nowrap;">
                <span style="float:left;">[{oxmultilang ident="SHOP_MODULE_`$module_var`"}]</span>
            </dd>
            <div class="spacer"></div>
        </dl>
    [{/if}]
    [{if $module_var == 'sStripeLivePk'}]
        <dl>
            <dt></dt>
            <dd>[{oxmultilang ident="STRIPE_CONNECTION_DATA"}] <a href="https://dashboard.stripe.com" target="_blank">https://dashboard.stripe.com</a></dd>
            <div class="spacer"></div>
        </dl>
    [{/if}]
[{elseif $module_var == 'iStripeCronSecondChanceTimeDiff'}]
    <dl>
        <dt>
            <select class="select" name="confselects[[{$module_var}]]" [{ $readonly }]>
                [{foreach from=$oView->stripeSecondChanceDayDiffs() item=iDayDiff}]
                    <option value="[{$iDayDiff}]" [{if $confselects.$module_var == $iDayDiff}]selected[{/if}]>[{$iDayDiff}]&nbsp;[{if $iDayDiff == 1}][{oxmultilang ident="STRIPE_DAY"}][{else}][{oxmultilang ident="STRIPE_DAYS"}][{/if}]</option>
                [{/foreach}]
            </select>
            [{oxinputhelp ident="HELP_SHOP_MODULE_`$module_var`"}]
        </dt>
        <dd>
            [{oxmultilang ident="SHOP_MODULE_`$module_var`"}]
        </dd>
    </dl>
[{elseif $module_var == 'sStripeWebhookEndpoint'}]
    <dl>
        <dt>[{oxmultilang ident="SHOP_MODULE_`$module_var`"}]</dt>
        <dd>
            [{if !$oView->stripeWebhookCanCreate()}]
                <span id="stripe-config-webhook-status-nok" style="float:left;margin-left: 1em;color: crimson">
                    [{oxmultilang ident="STRIPE_KEY_NOT_CONFIGURED"}]
                </span>
            [{else}]
                <script type="text/javascript">
                function stripeCreateWebhook()
                {
                    var mode = document.getElementsByName('confselects[sStripeMode]')[0];
                    var url = '[{$oView->stripeGetWebhookCreateUrl()}]';
                    url += '&mode=' + mode.value;
                    var xhttp = new XMLHttpRequest();
                    xhttp.onload = function() {
                        var response = JSON.parse(this.responseText);
                        if(response.status === 'SUCCESS') {
                            document.getElementById('sStripeWebhookEndpoint').value = response.body.endpointId;
                            document.getElementById('stripe-config-webhook-force-section').style.display = '';
                            document.getElementById('stripe-config-webhook-status-ok').style.display = '';
                            document.getElementById('stripe-config-webhook-status-nok').style.display = 'none';
                            document.getElementById('stripe-config-webhook-button-create').style.display = 'none';
                            document.getElementById('[{$module_var}]_force_refresh').checked = false;
                        } else {
                            document.getElementById('stripe-config-webhook-force-section').style.display = 'none';
                            document.getElementById('stripe-config-webhook-status-ok').style.display = 'none';
                            document.getElementById('stripe-config-webhook-status-nok').style.display = '';
                            document.getElementById('stripe-config-webhook-button-create').style.display = '';
                        }
                    }
                    xhttp.open('GET', url)
                    xhttp.send();
                }

                function updateWebhookConfigForm(target) {
                    var button = document.getElementById('stripe-config-webhook-button-create');
                    if (target.checked) {
                        button.style.display = '';
                    } else {
                        button.style.display = 'none';
                    }
                }
                </script>

                <input type="hidden" id="[{$module_var}]" name="confstr[[{$module_var}]]" value="[{$confstrs.$module_var}]" />
                <input type="hidden" id="[{$module_var}]Secret" name="confstr[[{$module_var}]]Secret" value="[{$confstrs.$module_var}]Secret" />
                <span id="stripe-config-webhook-status-ok" style="float:left;margin-left: 1em;color: green;[{if !$oView->stripeIsWebhookReady()}]display: none;[{/if}]">
                    [{oxmultilang ident="STRIPE_WEBHOOK_SET"}]
                </span>
                <span id="stripe-config-webhook-status-nok" style="float:left;margin-left: 1em;color: crimson;[{if $oView->stripeIsWebhookReady()}]display: none;[{/if}]">
                    [{oxmultilang ident="STRIPE_WEBHOOK_MISSING"}]
                </span>
                <br/>
                <div id="stripe-config-webhook-force-section" [{if !$oView->stripeIsWebhookReady()}]style="display: none"[{/if}]>
                    <label for="[{$module_var}]_force_refresh">[{oxmultilang ident="STRIPE_CONFIG_WEBHOOK_FORCE"}]</label>
                    <input onchange="updateWebhookConfigForm(this)" type="checkbox" id="[{$module_var}]_force_refresh" value="0" />
                </div>
                <br />
                <button id="stripe-config-webhook-button-create" onclick="stripeCreateWebhook();return false;" [{if $oView->stripeIsWebhookReady()}]style="display: none"[{/if}]>
                    [{oxmultilang ident="STRIPE_CONFIG_WEBHOOK_CREATE"}]
                </button>
            [{/if}]
        </dd>
    </dl>
[{elseif $module_var == 'sStripeWebhookEndpointSecret'}]
[{elseif $module_var == 'sStripeTestKey' || $module_var == 'sStripeLiveKey'}]
    <dl>
        <dt>
            <div>
                <input type="password" class="txt" style="width: 250px;" name="confstrs[[{$module_var}]]" value="[{$confstrs.$module_var}]" [{$readonly}]>
                [{oxinputhelp ident="HELP_SHOP_MODULE_`$module_var`"}]
            </div>
        </dt>
        <dd style="white-space: nowrap;">
            <span style="float:left;">[{oxmultilang ident="SHOP_MODULE_`$module_var`"}]</span>
        </dd>
        <div class="spacer"></div>
    </dl>
[{else}]
    [{$smarty.block.parent}]
[{/if}]
