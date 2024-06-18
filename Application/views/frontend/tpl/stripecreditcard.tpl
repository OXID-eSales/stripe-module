[{assign var="oPaymentModel" value=$paymentmethod->getStripePaymentModel()}]

<input type="hidden" name="dynvalue[stripe_token_id]" id="stripe_token_id">
<div id="stripe_creditcard_error_box" class="form-group" style="display:none;">
    <div class="col-lg-3"></div>
    <div class="col-lg-9">
        <div class="form-control" style="background-color:#ff5959" id="stripe_creditcard_error">[{oxmultilang ident="ERROR"}]</div>
    </div>
</div>

[{if $oView->stripeGetUsedCards()}]
    <div class="form-group">
        <label for="stripe_used_card" class="req control-label col-lg-3">[{oxmultilang ident="STRIPE_CARDS_USED"}]</label>
        <div class="col-lg-9">
            <select class="form-control" id="stripe_used_card" name="dynvalue[stripe_used_card]" required="required">
                <option value="">[{oxmultilang ident="STRIPE_PLEASE_SELECT"}]</option>
                [{foreach from=$oView->stripeGetUsedCards() item=card}]
                    <option value="[{$card.id}]">[{$card.title}] ([{$card.expire}])</option>
                [{/foreach}]
                <option value="new">[{oxmultilang ident="STRIPE_NEW_CARD"}]</option>
            </select>
            [{foreach from=$oView->stripeGetUsedCards() item=card}]
            <input type="hidden" id="stripe_card_known_holder_[{$card.id}]" name="dynvalue[stripe_card_holder][]" value="[{$card.holder}]" />
            [{/foreach}]
        </div>
    </div>
[{/if}]

<div id="stripe_new_card"[{if $oView->stripeGetUsedCards()}] style="display:none;"[{/if}]>
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
</div>

[{oxscript include="https://js.stripe.com/v3/"}]
[{capture name="stripeComponentsLoad"}]
    [{if false}]<script>[{/if}]
    const pubKey = '[{$oPaymentModel->getPublishableKey()}]';
    if (pubKey === '') {
        document.getElementById('[{$sInputName}]_error').innerHTML = '[{oxmultilang ident="STRIPE_ERROR_ORDER_CONFIG_PUBKEY"}]';
        document.getElementById('[{$sInputName}]_error_box').style.display = '';
    } else {
        [{if $oView->stripeGetUsedCards()}]
        $("#stripe_used_card").change(function() {
            const val = $(this).val();
            if (val === "new") {
                $("#stripe_new_card").show();
            }
            else {
                $("#stripe_new_card").hide();
            }
        });
        [{/if}]

        if (!stripe) {
            var stripe = Stripe(pubKey);
        }

        const elements = stripe.elements(),
            cardElement = elements.create('card', {hidePostalCode: true}),
            paymentForm = document.getElementById('payment');

        let displayErrorBox = document.getElementById('stripe_creditcard_error_box');
        let displayError = document.getElementById('stripe_creditcard_error');

        cardElement.mount('#stripeCardElement');
        cardElement.on('change', ({error}) => {
            let stripeUsedCard = '';
            if (paymentForm.elements['stripe_used_card']) {
                stripeUsedCard = paymentForm.elements['stripe_used_card'].value;
            }
            if (error && (!stripeUsedCard || stripeUsedCard === 'new')) {
                displayError.textContent = error.message;
                displayErrorBox.style.display = 'block';
            } else {
                displayError.textContent = '';
                displayErrorBox.style.display = 'none';
            }
        });

        paymentForm.addEventListener('submit', function(event) {
            let stripeUsedCard = '';
            if (paymentForm.elements['stripe_used_card']) {
                stripeUsedCard = paymentForm.elements['stripe_used_card'].value;
            }

            if (paymentForm.elements['payment_stripecreditcard'].checked === true) {
                event.preventDefault();

                const holder = document.getElementById('stripe_card_holder').value;
                if (!holder && stripeUsedCard && stripeUsedCard !== 'new') {
                    paymentForm.submit();
                }

                stripe.createToken(cardElement, {name: holder})
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
    [{if false}]</script>[{/if}]
[{/capture}]
[{oxscript add=$smarty.capture.stripeComponentsLoad}]
