[{if !method_exists($paymentmethod, 'isStripePaymentMethod') || $paymentmethod->isStripePaymentMethod() === false}]
    [{$smarty.block.parent}]
[{else}]
    [{assign var="paymentModel" value=$paymentmethod->getStripePaymentModel()}]
    <div class="well well-sm" id="container_[{$sPaymentID}]" [{if $paymentModel->isStripeMethodHiddenInitially()}]style="display:none"[{/if}]>
        [{include file="page/checkout/inc/payment_other.tpl"}]
    </div>
[{/if}]
