<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Controller;

use OxidSolutionCatalysts\Stripe\Application\Helper\Payment;
use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Registry;
use Stripe\Webhook;

class StripeWebhook extends FrontendController
{
    /**
     * @var string
     */
    protected $_sThisTemplate = '@stripe/stripewebhook';

    /**
     * The render function
     */
    public function render()
    {
        $sEndpointSecret = Payment::getInstance()->getWebhookEndpointSecret();

        $sPayload = @file_get_contents('php://input', false, null, 0, 1048576);
        $sSigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        if (empty($sSigHeader)) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Missing Stripe signature header';
            exit();
        }
        try {
            $event = Webhook::constructEvent($sPayload, $sSigHeader, $sEndpointSecret);
        } catch(\UnexpectedValueException $oEx) {
            // Invalid payload
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            echo Registry::getLang()->translateString('STRIPE_WEBHOOK_EVENT_UNEXPECTED');
            exit();
        } catch(\Stripe\Exception\SignatureVerificationException $oEx) {
            // Invalid signature
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            echo Registry::getLang()->translateString('STRIPE_WEBHOOK_SIGNATURE_FAILED');
            exit();
        } catch (\Exception $oEx) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Invalid webhook';
            exit();
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $sPaymentIntentId = $event->data->object->id;
                if (!empty($sPaymentIntentId)) {
                    $oOrder = oxNew(Order::class);
                    if ($oOrder->stripeLoadOrderByTransactionId($sPaymentIntentId) === true) {
                        $oOrder->stripeGetPaymentModel()->getTransactionHandler()->processTransaction($oOrder);
                    } else {
                        // Throw HTTP error when order not found, this will trigger Stripe to retry sending the status
                        // For some payment methods the webhook is called before the order exists
                        Registry::getUtils()->setHeader("HTTP/1.1 409 Conflict");
                        Registry::getUtils()->showMessageAndExit("");
                    }
                }
                break;
            case 'payment_intent.payment_failed' :
                $sPaymentIntentId = $event->data->object->id;
                if (!empty($sPaymentIntentId)) {
                    $oOrder = oxNew(Order::class);
                    if ($oOrder->stripeLoadOrderByTransactionId($sPaymentIntentId) === true) {
                        $oOrder->stripeSetFolder('ORDERFOLDER_PROBLEMS');
                    }
                }
                break;
            case 'charge.refunded' :
                $sPaymentIntentId = $event->data->object->payment_intent;
                if (!empty($sPaymentIntentId)) {
                    $oOrder = oxNew(Order::class);
                    if ($oOrder->stripeLoadOrderByTransactionId($sPaymentIntentId) === true) {
                        $oOrder->stripeSetFolder('ORDERFOLDER_FINISHED');
                    }
                }
                break;
            default:
                echo 'Received unknown event type ' . $event->type;
        }

        return $this->_sThisTemplate;
    }

    /**
     * Method trying to delete configured webhook endpoint
     *
     * @return bool
     */
    protected function stripeDeleteWebhookEndpoint()
    {
        $oPaymentHelper = Payment::getInstance();
        $sStripeConfiguredWebhookEndpoint = $oPaymentHelper->getWebhookEndpointId();
        if (empty($sStripeConfiguredWebhookEndpoint)) {
            return true;
        }

        $oStripeWebhookEndpoint = $oPaymentHelper->stripeRetrieveWebhookEndpoint($oPaymentHelper->getWebhookEndpointId());
        if (!$oStripeWebhookEndpoint || $oStripeWebhookEndpoint->isDeleted()) {
            $oPaymentHelper->stripeDeleteWebhookParameter();
            return true;
        }

        try {
            $oPaymentHelper->loadStripeApiWithToken($oPaymentHelper->getStripeKey($oPaymentHelper->getStripeMode()))->webhookEndpoints->delete($sStripeConfiguredWebhookEndpoint);
        } catch (\Exception $oEx) {
            Registry::getLogger()->error($oEx);
            return false;
        }

        $oPaymentHelper->stripeDeleteWebhookParameter();

        return true;
    }
}
