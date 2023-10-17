<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Helper;

use OxidSolutionCatalysts\Stripe\Application\Model\Payment\Base;
use OxidEsales\Eshop\Application\Model\Order as CoreOrder;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleSettingBridgeInterface;
use Stripe\StripeClient;
use Stripe\WebhookEndpoint;

class Payment
{
    /**
     * @var Payment
     */
    protected static $oInstance = null;

    /**
     * List of all available Stripe payment methods
     *
     * @var array
     */
    protected $aPaymentMethods = array(
        'stripebancontact'      => array('title' => 'Stripe Bancontact',        'model' => \OxidSolutionCatalysts\Stripe\Application\Model\Payment\Bancontact::class),
        'stripecreditcard'      => array('title' => 'Stripe Credit Card',       'model' => \OxidSolutionCatalysts\Stripe\Application\Model\Payment\Creditcard::class),
        'stripeeps'             => array('title' => 'Stripe EPS Österreich',    'model' => \OxidSolutionCatalysts\Stripe\Application\Model\Payment\Eps::class),
        'stripegiropay'         => array('title' => 'Stripe Giropay',           'model' => \OxidSolutionCatalysts\Stripe\Application\Model\Payment\Giropay::class),
        'stripeideal'           => array('title' => 'Stripe iDeal',             'model' => \OxidSolutionCatalysts\Stripe\Application\Model\Payment\Ideal::class),
        'stripep24'             => array('title' => 'Stripe Przelewy24',        'model' => \OxidSolutionCatalysts\Stripe\Application\Model\Payment\Przelewy24::class),
//        'stripepaypal'          => array('title' => 'Stripe Paypal',            'model' => \OxidSolutionCatalysts\Stripe\Application\Model\Payment\PayPal::class),   //Disabled because not working with Connect
        'stripesofort'          => array('title' => 'Stripe Sofort',            'model' => \OxidSolutionCatalysts\Stripe\Application\Model\Payment\Sofort::class),
    );

    /**
     * Array with information about all enabled Stripe payment types
     *
     * @var array|null
     */
    protected $aPaymentInfo = null;

    /**
     * Create singleton instance of payment helper
     *
     * @return Payment
     */
    static function getInstance()
    {
        if (self::$oInstance === null) {
            self::$oInstance = oxNew(self::class);
        }
        return self::$oInstance;
    }

    /**
     * Return all available Stripe payment methods
     *
     * @return array
     */
    public function getStripePaymentMethods()
    {
        $aPaymentMethods = array();
        foreach ($this->aPaymentMethods as $sPaymentId => $aPaymentMethodInfo) {
            $aPaymentMethods[$sPaymentId] = $aPaymentMethodInfo['title'];
        }
        return $aPaymentMethods;
    }

    /**
     * Determine if given paymentId is a Stripe payment method
     *
     * @param string $sPaymentId
     * @return bool
     */
    public function isStripePaymentMethod($sPaymentId)
    {
        return isset($this->aPaymentMethods[$sPaymentId]);
    }

    /**
     * Returns payment model for given paymentId
     *
     * @param string $sPaymentId
     * @return Base
     * @throws \Exception
     */
    public function getStripePaymentModel($sPaymentId)
    {
        if ($this->isStripePaymentMethod($sPaymentId) === false || !isset($this->aPaymentMethods[$sPaymentId]['model'])) {
            throw new \Exception('Stripe Payment method unknown - '.$sPaymentId);
        }

        $oPaymentModel = oxNew($this->aPaymentMethods[$sPaymentId]['model']);
        return $oPaymentModel;
    }

    /**
     * Returns configured mode of stripe
     *
     * @return string
     */
    public function getStripeMode()
    {
        return Registry::getConfig()->getShopConfVar('sStripeMode');
    }

    /**
     * Return Stripe access token for parameter mode
     *
     * @param string $sMode
     * @return string
     */
    public function getStripeToken($sMode = '')
    {
        if ($sMode == 'live') {
            return Registry::getConfig()->getShopConfVar('sStripeLiveToken');
        } elseif ($sMode == 'test') {
            return Registry::getConfig()->getShopConfVar('sStripeTestToken');
        }

        return '';
    }

    /**
     * Return Stripe private key for parameter mode
     *
     * @param string $sMode
     * @return string
     */
    public function getStripeKey($sMode = '')
    {
        if ($sMode == 'live') {
            return Registry::getConfig()->getShopConfVar('sStripeLiveKey');
        } elseif ($sMode == 'test') {
            return Registry::getConfig()->getShopConfVar('sStripeTestKey');
        }

        return '';
    }

    /**
     * Returns current Stripe publishable key for parameter mode
     *
     * @param string $sMode
     * @return string
     */
    public function getPublishableKey($sMode = '')
    {
        if ($sMode == 'live') {
            return Registry::getConfig()->getShopConfVar('sStripeLivePk');
        } elseif ($sMode == 'test') {
            return Registry::getConfig()->getShopConfVar('sStripeTestPk');
        }

        return '';
    }

    /**
     * Collect information about all activated Stripe payment types
     *
     * @return array
     */
    public function getStripePaymentInfo()
    {
        if ($this->aPaymentInfo === null) {
            $aPaymentInfo = [];
            try {
                foreach ($this->aPaymentMethods as $sId => $aPaymentMethod) {
                    $aPaymentInfo[$sId] = [
                        'title' => $aPaymentMethod['title'],
                        'minAmount' => 0,
                        'maxAmount' => 999999,
                    ];
                }
            } catch (\Exception $oEx) {
                Registry::getLogger()->error($oEx->getMessage());
            }
            $this->aPaymentInfo = $aPaymentInfo;
        }
        return $this->aPaymentInfo;
    }

    /**
     * Check if connection with token can be established
     *
     * @param  string $sTokenConfVar
     * @return bool
     */
    public function isConnectionWithTokenSuccessful($sTokenConfVar)
    {
        $sStripeToken = Registry::getConfig()->getShopConfVar($sTokenConfVar);
        try {
            $aStripeInfo = $this->loadStripeApiWithToken($sStripeToken)->customers->all();
            if (empty($aStripeInfo)) {
                return false;
            }
        } catch (\Exception $oEx) {
            return false;
        }
        return true;
    }

    /**
     * Returns Stripe Client
     *
     * @return \Stripe\StripeClient
     * @throws \Exception
     */
    public function loadStripeApi($sMode = '')
    {
        if (empty($sMode)) {
            $sMode = $this->getStripeMode();
        }
        $sStripeToken = $this->getStripeToken($sMode);
        return $this->loadStripeApiWithToken($sStripeToken);
    }

    /**
     * @param string $sStripeToken
     * @return StripeClient
     * @throws \Exception
     */
    public function loadStripeApiWithToken($sStripeToken)
    {
        try {
            if (!$sStripeToken) {
                throw new \Exception(Registry::getLang()->translateString('STRIPE_CLIENT_MISSING_API_KEY_ERROR'));
            }

            if (class_exists('Stripe\StripeClient')) {
                return new StripeClient($sStripeToken);
            } else {
                throw new \Exception(Registry::getLang()->translateString('STRIPE_CLIENT_MISSING_API_CLASS_ERROR'));
            }
        } catch(\Exception $oEx) {
            Registry::getLogger()->error($oEx->getMessage());
            throw new \Exception(Registry::getLang()->translateString('STRIPE_CLIENT_CONNECTION_ERROR'));
        }
    }

    /**
     * Generates locale string
     * Oxid doesn't have a locale logic, so solving it with by using the language files
     *
     * @return string
     */
    public function getLocale()
    {
        $sLocale = Registry::getLang()->translateString('STRIPE_LOCALE');
        if (Registry::getLang()->isTranslated() === false) {
            $sLocale = 'en_US'; // default
        }
        return $sLocale;
    }

    /**
     * Returns a floating price as integer in cents
     *
     * @param float $fPrice
     * @return int
     */
    public function priceInCent(float $fPrice)
    {
        return (int) number_format($fPrice * 100, 0,'','');
    }

    /**
     * Returns matching api endpoint the given order was created in
     *
     * @param  CoreOrder $oOrder
     * @return StripeClient
     */
    public function getApiClientByOrder(CoreOrder $oOrder)
    {
        $sMode = $oOrder->oxorder__stripemode->value;
        if (empty($sMode)) {
            $sMode = false;
        }

        return $this->loadStripeApi($sMode);
    }

    public function stripeIsTokenConfigured()
    {
        $sMode = $this->getStripeMode();
        if (!$this->getStripeToken($sMode)) {
            return false;
        }
        return true;
    }

    public function stripeIsKeyConfigured()
    {
        $sMode = $this->getStripeMode();
        if (!$this->getStripeKey($sMode)) {
            return false;
        }
        return true;
    }

    /**
     * Return the Stripe webhook url
     *
     * @return string
     */
    public function getWebhookUrl()
    {
        return Registry::getConfig()->getCurrentShopUrl().'index.php?cl=stripeWebhook';
    }

    /**
     * Return the Stripe webhook url
     *
     * @return string
     */
    public function getWebhookEndpointId()
    {
        return Registry::getConfig()->getConfigParam('sStripeWebhookEndpoint');
    }

    /**
     * Return the Stripe webhook object if found
     *
     * @return WebhookEndpoint|null
     */
    public function stripeRetrieveWebhookEndpoint($sStripeWebhookEndpointId)
    {
        $sPrivateKey = $this->getStripeKey($this->getStripeMode());
        try {
            return $this->loadStripeApiWithToken($sPrivateKey)->webhookEndpoints->retrieve($sStripeWebhookEndpointId);
        } catch (\Exception $oEx) {
            return null;
        }
    }

    /**
     * @return bool
     */
    public function stripeIsWebhookConfigured()
    {
        return !empty($this->getWebhookEndpointId());
    }

    /**
     * @param WebhookEndpoint $oStripeWebhookEndpoint
     * @return bool
     */
    public function stripeIsWebhookValid(WebhookEndpoint $oStripeWebhookEndpoint)
    {
        return $oStripeWebhookEndpoint->status == 'enabled';
    }

    /**
     * Deletes coonfigured webhook endpoint and secret
     *
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function stripeDeleteWebhookParameter()
    {
        Registry::getConfig()->setConfigParam('sStripeWebhookEndpoint', '');
        Registry::getConfig()->setConfigParam('sStripeWebhookEndpointSecret', '');
        $moduleSettingService = ContainerFactory::getInstance()->getContainer()->get(ModuleSettingBridgeInterface::class);
        $moduleSettingService->save('sStripeWebhookEndpoint', '', 'stripe');
        $moduleSettingService->save('sStripeWebhookEndpointSecret', '', 'stripe');
    }
}
