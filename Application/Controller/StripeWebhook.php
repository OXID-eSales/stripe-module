<?php
/**
 * Copyright © FATCHIP GmbH. All rights reserved.
 * See LICENSE file for license details.
 */

namespace FC\stripe\Application\Controller;

use FC\stripe\Application\Helper\Payment;
use OxidEsales\Eshop\Application\Controller\FrontendController;
use OxidEsales\Eshop\Application\Model\Order;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleSettingBridgeInterface;
use Stripe\Webhook;

class StripeWebhook extends FrontendController
{
    /**
     * @var string
     */
    protected $_sThisTemplate = 'stripewebhook.tpl';

    /**
     * Method creating a webhook endpoint on Stripe connected account
     * Tries to delete first the currently configured one if any.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function createWebhookEndpoint()
    {
        $blDeleted = $this->stripeDeleteWebhookEndpoint();
        if (!$blDeleted) {
            echo json_encode([
                'code' => 400,
                'status' => 'ERROR',
                'body' => [
                    'message' => Registry::getLang()->translateString('STRIPE_WEBHOOK_CREATE_ERROR_DELETE_FAILED'),
                ],
            ]);

            exit();
        }

        try {
            $oPaymentHelper = Payment::getInstance();
            $sMode = Registry::getConfig()->getRequestEscapedParameter('mode') ?? '';
            $sPrivateKey = $sMode == 'test' ? Registry::getConfig()->getConfigParam('sStripeTestKey') : Registry::getConfig()->getConfigParam('sStripeLiveKey');
            $oApi = $oPaymentHelper->loadStripeApiWithToken($sPrivateKey);
            $sUrl = $oPaymentHelper->getWebhookUrl();
            $oWebhookEndpoint = $oApi->webhookEndpoints->create([
                'url' => $sUrl,
                'enabled_events' => [
                    'payment_intent.payment_failed',
                    'payment_intent.succeeded',
                    'charge.refunded',
                ],
                'connect' => true
            ]);

            if ($oWebhookEndpoint) {
                $moduleSettingService = ContainerFactory::getInstance()->getContainer()->get(ModuleSettingBridgeInterface::class);
                $moduleSettingService->save('sStripeWebhookEndpoint', $oWebhookEndpoint->id, 'stripe');
                $moduleSettingService->save('sStripeWebhookEndpointSecret', $oWebhookEndpoint->secret, 'stripe');

                echo json_encode([
                    'code' => 200,
                    'status' => 'SUCCESS',
                    'body' => [
                        'endpointId' => $oWebhookEndpoint->id
                    ],
                ]);
            } else {
                echo json_encode([
                    'code' => 400,
                    'status' => 'ERROR',
                    'body' => [
                        'message' => Registry::getLang()->translateString('STRIPE_WEBHOOK_CREATE_ERROR'),
                    ],
                ]);
            }
        } catch (\Exception $oEx) {
            echo json_encode([
                'code' => 400,
                'status' => 'ERROR',
                'body' => [
                    'message' => Registry::getLang()->translateString('STRIPE_WEBHOOK_CREATE_ERROR').':'.$oEx->getMessage(),
                ],
            ]);
        }

        exit();
    }

    /**
     * The render function
     */
    public function render()
    {
        $sEndpointSecret = Registry::getConfig()->getConfigParam('sStripeWebhookEndpointSecret');

        $sPayload = @file_get_contents('php://input');
        $sSigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        try {
            $event = Webhook::constructEvent($sPayload, $sSigHeader, $sEndpointSecret);
        } catch(\UnexpectedValueException $oEx) {
            // Invalid payload
            http_response_code(400);
            echo Registry::getLang()->translateString('STRIPE_WEBHOOK_EVENT_UNEXPECTED').':'.$oEx->getMessage();
            exit();
        } catch(\Stripe\Exception\SignatureVerificationException $oEx) {
            // Invalid signature
            http_response_code(400);
            echo Registry::getLang()->translateString('STRIPE_WEBHOOK_SIGNATURE_FAILED').':'.$oEx->getMessage();
            exit();
        } catch (\Exception $oEx) {
            http_response_code(400);
            echo $oEx->getMessage();
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
