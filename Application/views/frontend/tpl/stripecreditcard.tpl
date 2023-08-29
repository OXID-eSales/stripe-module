[{assign var="oPaymentModel" value=$paymentmethod->getStripePaymentModel()}]

<input type="hidden" name="dynvalue[stripe_token_id]" id="stripe_token_id">
<div id="stripe_creditcard_error_box" class="form-group" style="display:none;">
    <div class="col-lg-3"></div>
    <div class="col-lg-9">
        <div class="form-control" style="background-color:#ff5959" id="stripe_creditcard_error">[{oxmultilang ident="ERROR"}]</div>
    </div>
</div>

<div class="form-group">
    <label for="stripe_card_holder" class="req control-label col-lg-3">[{oxmultilang ident="BANK_ACCOUNT_HOLDER"}]</label>
    <div class="col-lg-9">
        <input type="text" id="stripe_card_holder" class="form-control" name="dynvalue[stripe_card_holder]" required="required" />
    </div>
</div>

<div class="form-group">
    <label class="req control-label col-lg-3">[{oxmultilang ident="CREDITCARD"}]</label>
    <div class="col-lg-9">
        <div id="stripeCardElement" class="form-control"></div>
    </div>
</div>

[{oxstyle include=$oViewConf->getModuleUrl('stripe','out/src/css/stripe.css')}]
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
        var cardElement = elements.create('card', {hidePostalCode: true});
        cardElement.mount('#stripeCardElement');

        let displayErrorBox = document.getElementById('stripe_creditcard_error_box');
        let displayError = document.getElementById('stripe_creditcard_error');

        cardElement.on('change', ({error}) => {
            if (error) {
                displayError.textContent = error.message;
                displayErrorBox.style.display = 'block';
            } else {
                displayError.textContent = '';
                displayErrorBox.style.display = 'none';
            }
        });

        var paymentForm = document.getElementById('payment');
        paymentForm.addEventListener('submit', function(event) {
            if (paymentForm.elements['payment_stripecreditcard'].checked === true) {
                event.preventDefault();

                stripe.createToken(cardElement, {name: document.getElementById('stripe_card_holder').value})
                .then(function(result) {
                    if (result.error) {
                        displayError.textContent = result.error.message;
                        displayErrorBox.style.display = 'block';
                    } else {
                        displayError.textContent = '';
                        displayErrorBox.style.display = 'none';
                        document.getElementById('stripe_token_id').value = result.token.id;
                        paymentForm.submit();
                    }
                });
            }
        });
    }
[{/capture}]
[{oxscript add=$smarty.capture.stripeComponentsLoad}]
