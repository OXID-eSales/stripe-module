<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Application\Controller\Admin;

use OxidEsales\Eshop\Application\Controller\Admin\AdminController;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleSettingBridge;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleSettingBridgeInterface;
use OxidEsales\Eshop\Core\Registry;

class StripeConnect extends AdminController
{
    /** @var string */
    protected $_sThisTemplate = "stripe_connect.tpl";

    /** @var ModuleSettingBridge */
    private ModuleSettingBridge $moduleSettingService;

    public function __construct()
    {
        parent::__construct();

        $this->moduleSettingService = ContainerFactory::getInstance()->getContainer()->get(ModuleSettingBridgeInterface::class);
    }

    /**
     * Landing point when returning from Stripe OnBoarding process
     *
     * @return false|void
     */
    public function stripeFinishOnBoarding()
    {
        if (!Registry::getSession()->checkSessionChallenge()) {
            return false;
        }
        $sAccessToken = Registry::getConfig()->getRequestEscapedParameter('access_token');
        $sPublishableKey = Registry::getConfig()->getRequestEscapedParameter('publishable_key');
        $sMode = Registry::getConfig()->getRequestEscapedParameter('shop_param');

        $blSuccess = true;
        if (empty($sAccessToken) || empty($sMode) || ($sMode != 'test' && $sMode != 'live')) {
            $blSuccess = false;
        } else {
            if ($sMode == 'live') {
                $this->moduleSettingService->save('sStripeLiveToken', $sAccessToken, 'stripe');
                $this->moduleSettingService->save('sStripeLivePk', $sPublishableKey, 'stripe');
            } else {
                $this->moduleSettingService->save('sStripeTestToken', $sAccessToken, 'stripe');
                $this->moduleSettingService->save('sStripeTestPk', $sPublishableKey, 'stripe');
            }
        }

        $aViewData = $this->getViewData();
        $aViewData['blIsSuccess'] = $blSuccess;
        $aViewData['backToAdminUrl'] = $this->getViewConfig()->getSslSelfLink();
        $this->setViewData($aViewData);
    }
}
