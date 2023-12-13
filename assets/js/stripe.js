/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

function stripeGetSelectedPaymentMethod() {
    var paymentForm = document.getElementById('payment');
    if (paymentForm && paymentForm.paymentid) {
        if (paymentForm.paymentid.length) {
            for (var i = 0; i < paymentForm.paymentid.length; i++) {
                if (paymentForm.paymentid[i].checked === true) {
                    return paymentForm.paymentid[i].value;
                }
            }
        } else {
            return paymentForm.paymentid.value;
        }
    }
    return false;
}

function stripeSubmitPaymentForm(paymentForm) {
    var checkedValue = stripeGetSelectedPaymentMethod();
    if (checkedValue === 'stripecreditcard') {
        stripeSubmitCCForm();
    } else {
        paymentForm.submit();
    }
}

function stripeSubmitCCForm() {
    stripe.createToken(cardElement, {name: document.getElementById('stripe_card_holder').value})
        .then(
            function (result){
                if (result.error) {
                    stripeCCTokenFailed(result.error)
                } else {
                    stripeCCTokenCreated(result);

                    var paymentForm = document.getElementById('payment');
                    paymentForm.submit();
                }
            },
            stripeCCTokenFailed
        );
}

function stripeCCTokenCreated(result) {
    let displayErrorBox = document.getElementById('stripe_creditcard_error_box');
    let displayError = document.getElementById('stripe_creditcard_error');
    displayError.textContent = '';
    displayErrorBox.style.display = 'none';
    document.getElementById('stripe_token_id').value = result.token.id;
}

function stripeCCTokenFailed(error) {
    let displayErrorBox = document.getElementById('stripe_creditcard_error_box');
    let displayError = document.getElementById('stripe_creditcard_error');
    displayError.textContent = error.message;
    displayErrorBox.style.display = 'block';
}