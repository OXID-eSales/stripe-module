<input type="hidden" name="dynvalue[[{$sInputName}]]" id="[{$sInputName}]">
<div id="[{$sInputName}]_error_box" class="form-group" style="display:none;">
    <div class="col-lg-3"></div>
    <div class="col-lg-9">
        <div class="form-control" style="background-color:#ff5959" id="[{$sInputName}]_error">[{oxmultilang ident="ERROR"}]</div>
    </div>
</div>
<div class="form-group">
    <label class="req control-label col-lg-3">[{oxmultilang ident="STRIPE_SELECT_BANK"}]</label>
    <div class="col-lg-9">
        <div id="[{$sInputName}]_select" class="form-control"></div>
    </div>
</div>
[{oxscript include="https://js.stripe.com/v3/"}]
[{capture name="stripeComponentsLoad"}]
    var pubKey = '[{$oPaymentModel->getPublishableKey()}]';
    if (pubKey == '') {
        document.getElementById('[{$sInputName}]_error').innerHTML = '[{oxmultilang ident="STRIPE_ERROR_ORDER_CONFIG_PUBKEY"}]';
        document.getElementById('[{$sInputName}]_error_box').style.display = '';
    } else {
        if (!stripe) {
            var stripe = Stripe(pubKey);
        }
        var elements = stripe.elements();
        var bankElement = elements.create('[{$sBankType}]', {
            classes: {
                base: 'form-control stripe-issuers'
            },
            value: '[{$sSavedValue}]'
        });
        bankElement.mount('#[{$sInputName}]_select');

        bankElement.on('change', function(event) {
            if (event.error) {
                document.getElementById('[{$sInputName}]_error').innerHTML = event.error;
                document.getElementById('[{$sInputName}]error_box').style.display = '';
            } else {
                document.getElementById('[{$sInputName}]_error').innerHTML = '';
                document.getElementById('[{$sInputName}]_error_box').style.display = 'none';
                document.getElementById('[{$sInputName}]').value = event.value;
            }
        });
    }
[{/capture}]
[{oxscript add=$smarty.capture.stripeComponentsLoad}]