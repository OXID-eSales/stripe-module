[{include file="headitem.tpl" title="GENERAL_ADMIN_TITLE"|oxmultilangassign}]

<style>
    .refundTable TD {
        padding-top: 10px;
        padding-bottom: 10px;
    }
    TD.borderTop {
        border-top: 1px solid black!important;
    }
    FIELDSET {
        border-radius: 15px;
        margin-bottom: 20px;
        padding: 10px;
    }
    FIELDSET.fullRefund SPAN{
        margin-left: 2px;
    }
    FIELDSET .refundSubmit {
        margin-top: 15px;
    }
    .typeSelect {
        margin-bottom: 10px;
    }
    FIELDSET.refundError {
        background-color: #FF8282;
        color: black;
        border: 3px solid #F00000;
    }
    FIELDSET.refundNotice {
        background-color: #ffeeb5;
        border: 3px solid #FFE385;
    }
    FIELDSET.refundSuccess {
        background-color: #7aff9e;
        border: 3px solid #00b02f;
    }
    FIELDSET.message STRONG {
        display: block;
        margin-bottom: 10px;
    }
</style>

[{if $readonly}]
    [{assign var="readonly" value="readonly disabled"}]
[{else}]
    [{assign var="readonly" value=""}]
[{/if}]

<form name="transfer" id="transfer" action="[{$oViewConf->getSelfLink()}]" method="post">
    [{$oViewConf->getHiddenSid()}]
    <input type="hidden" name="oxid" value="[{$oxid}]">
    <input type="hidden" name="cl" value="stripe_order_refund">
</form>
[{if $oView->isStripeOrder() === true}]
    [{if $oView->wasRefundSuccessful() == true}]
        <fieldset class="refundSuccess message">
            [{oxmultilang ident="STRIPE_REFUND_SUCCESSFUL"}]
        </fieldset>
    [{/if}]
    [{if $oView->getErrorMessage() != false}]
        <fieldset class="refundError message">
            <strong>Error</strong>
            [{$oView->getErrorMessage()}]
        </fieldset>
    [{/if}]

    [{assign var="blIsOrderRefundable" value=$oView->isOrderRefundable()}]
    [{if $blIsOrderRefundable == false}]
        <fieldset class="refundNotice message">
            <strong>[{oxmultilang ident="STRIPE_NOTICE"}]</strong>
            [{oxmultilang ident="STRIPE_ORDER_NOT_REFUNDABLE"}]
        </fieldset>
    [{/if}]

    [{assign var="order" value=$oView->getOrder()}]
    [{assign var="paymentType" value=$order->getPaymentType()}]
    [{assign var="paymentExtraInfo" value=$order->stripeGetExtraInfo()}]
    <fieldset>
        <legend>[{oxmultilang ident="STRIPE_PAYMENT_DETAILS"}]</legend>
        <table>
            <tr>
                <td class="edittext">
                    [{oxmultilang ident="STRIPE_PAYMENT_TYPE"}]:
                </td>
                <td class="edittext">
                    [{$paymentType->oxpayments__oxdesc->value}]
                </td>
                <td class="edittext"></td>
            </tr>
            <tr>
                <td class="edittext">
                    [{oxmultilang ident="STRIPE_TRANSACTION_ID"}]:
                </td>
                <td class="edittext">
                    [{$order->oxorder__oxtransid->value}]
                </td>
                <td class="edittext"></td>
            </tr>
            [{if $order->oxorder__stripeexternaltransid->value != ""}]
                <tr>
                    <td class="edittext">
                        [{oxmultilang ident="STRIPE_EXTERNAL_TRANSACTION_ID"}]:
                    </td>
                    <td class="edittext">
                        [{$order->oxorder__stripeexternaltransid->value}]
                    </td>
                    <td class="edittext"></td>
                </tr>
            [{/if}]
            [{if $paymentExtraInfo != ""}]
                <tr>
                    <td class="edittext">
                        [{oxmultilang ident="STRIPE_ORDER_EXTRA_INFO"}]:
                    </td>
                    <td class="edittext">
                        [{$paymentExtraInfo}]
                    </td>
                    <td class="edittext"></td>
                </tr>
            [{/if}]
        </table>
    </fieldset>

    [{if $order->stripeIsEligibleForPaymentFinish()}]
        <fieldset>
            <legend>[{oxmultilang ident="STRIPE_SUBSEQUENT_ORDER_COMPLETION"}]</legend>
            [{oxmultilang ident="STRIPE_ORDER_PAYMENT_URL"}]: <a href="[{$order->stripeGetPaymentFinishUrl()}]" target="_blank" style="text-decoration: underline;">[{$order->stripeGetPaymentFinishUrl()}]</a><br><br>
            <form action="[{$oViewConf->getSelfLink()}]" method="post">
                [{$oViewConf->getHiddenSid()}]
                <input type="hidden" name="cl" value="stripe_order_refund">
                <input type="hidden" name="oxid" value="[{$oxid}]">
                <input type="hidden" name="fnc" value="sendSecondChanceEmail">
                <input type="submit" value="[{oxmultilang ident="STRIPE_SEND_SECOND_CHANCE_MAIL"}]">
                [{if $order->oxorder__stripesecondchancemailsent->value != "0000-00-00 00:00:00"}]
                    <span style="color: crimson;">[{oxmultilang ident="STRIPE_SECOND_CHANCE_MAIL_ALREADY_SENT"}] ( [{$order->oxorder__stripesecondchancemailsent->value}] )</span>
                [{/if}]
            </form>
        </fieldset>
    [{/if}]

    [{if $blIsOrderRefundable == true}]
        <fieldset class="fullRefund">
            <legend>[{oxmultilang ident="STRIPE_FULL_REFUND"}]</legend>
            <form name="search" id="search" action="[{$oViewConf->getSelfLink()}]" method="post">
                [{$oViewConf->getHiddenSid()}]
                <input type="hidden" name="cl" value="stripe_order_refund">
                <input type="hidden" name="oxid" value="[{$oxid}]">
                <input type="hidden" name="fnc" value="fullRefund">
                [{assign var="blIsFullRefundAvailable" value=$oView->isFullRefundAvailable()}]
                [{if $blIsFullRefundAvailable == true}]
                    <span>[{oxmultilang ident="STRIPE_FULL_REFUND_TEXT"}]: [{$oView->getFormatedPrice($edit->oxorder__oxtotalordersum->value)}] <small>[{$edit->oxorder__oxcurrency->value}]</small></span><br><br>
                [{else}]
                    <input type="hidden" name="refundRemaining" value="1">
                    <span>[{oxmultilang ident="STRIPE_REFUND_REMAINING"}]: [{$oView->getFormatedPrice($oView->getRemainingRefundableAmount())}] <small>[{$edit->oxorder__oxcurrency->value}]</small></span><br><br>
                [{/if}]
                <span><label for="refund_reason">[{oxmultilang ident="STRIPE_REFUND_REASON"}]:</label></span>
                <select id="refund_reason" name="refund_reason">
                    <option value="">[{oxmultilang ident="STRIPE_PLEASE_SELECT"}]</option>
                    <option value="duplicate">[{oxmultilang ident="STRIPE_REFUND_DUPLICATE"}]</option>
                    <option value="requested_by_customer">[{oxmultilang ident="STRIPE_REFUND_CUSTOMER"}]</option>
                    <option value="fraudulent">[{oxmultilang ident="STRIPE_REFUND_FRAUD"}]</option>
                </select><br/>
                <span><label for="refund_description">[{oxmultilang ident="STRIPE_REFUND_DESCRIPTION"}]:</label></span>
                <input type="text" name="refund_description" value="" placeholder="[{oxmultilang ident="STRIPE_REFUND_DESCRIPTION_PLACEHOLDER"}]" maxlength="140" size="120"><br>
                <input type="submit" value="[{oxmultilang ident="STRIPE_REFUND_SUBMIT"}]" class="refundSubmit">
            </form>
        </fieldset>
    [{/if}]
[{/if}]

[{include file="bottomnaviitem.tpl"}]
</table>
[{include file="bottomitem.tpl"}]
