[{if $edit !== null && $edit->isStripePaymentMethod() == 1}]
    [{assign var="paymentModel" value=$edit->getStripePaymentModel() }]
    <tr>
        <td class="edittext" colspan="2">
            <b>[{oxmultilang ident="STRIPE_IS_STRIPE"}]</b>
            <input type="hidden" name="stripe[api]" value="payment">
        </td>
    </tr>
    [{if method_exists($oView, 'stripeIsTokenConfigured') && $oView->stripeIsTokenConfigured() === false }]
        <tr>
            <td class="edittext" colspan="2">
                <b style="color: red;">[{oxmultilang ident="STRIPE_TOKEN_NOT_CONFIGURED"}]</b>
            </td>
        </tr>
    [{/if}]
    <tr id="stripe_payment_description">
        <td class="edittext" width="70">
            [{oxmultilang ident="STRIPE_PAYMENT_DESCRIPTION"}]
        </td>
        <td class="edittext">
            <input type="text" class="editinput" size="25" name="stripe[payment_description]" value="[{$paymentModel->getConfigParam('payment_description')}]" [{$readonly}]>
            [{oxinputhelp ident="STRIPE_PAYMENT_DESCRIPTION_HELP"}]
        </td>
    </tr>
    [{if $paymentModel->getCustomConfigTemplate() !== false}]
        [{include file=$paymentModel->getCustomConfigTemplate()}]
    [{/if}]
    <tr>
        <td class="edittext" colspan="2">
            &nbsp;<div style="display: none;" id="stripe_payment_min_max">
                [{assign var="oFrom" value=$paymentModel->getStripeFromAmount() }]
                [{assign var="oTo" value=$paymentModel->getStripeToAmount() }]
                [{if $oFrom}]<br>
                    [{oxmultilang ident="STRIPE_PAYMENT_LIMITATION"}]:<br>
                    [{oxmultilang ident="STRIPE_PAYMENT_LIMITATION_FROM"}] [{$oFrom->value}] [{$oFrom->currency}] [{oxmultilang ident="STRIPE_PAYMENT_LIMITATION_TO"}]
                    [{if $oTo != false}]
                        [{$oTo->value}] [{$oTo->currency}]
                    [{else}]
                        [{oxmultilang ident="STRIPE_PAYMENT_LIMITATION_UNLIMITED"}]
                    [{/if}]
                [{/if}]
            </div>
            <script>
                function appendMinMaxInfo() {
                    var minMaxInfo = document.getElementById("stripe_payment_min_max");
                    var clone = minMaxInfo.cloneNode(true);
                    clone.style.display = "";
                    document.getElementById("helpText_HELP_PAYMENT_MAIN_AMOUNT").parentNode.appendChild(clone);
                }
                setTimeout(appendMinMaxInfo, 100);
            </script>
        </td>
    </tr>
[{/if}]
[{$smarty.block.parent}]
