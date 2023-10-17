<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\extend\Core;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Framework\Templating\TemplateRendererBridgeInterface;

class Email extends Email_parent
{
    protected $_sStripeSecondChanceTemplate = 'stripe_second_chance.tpl';

    /**
     * Returns old or current template renderer
     *
     * @return \OxidEsales\EshopCommunity\Internal\Framework\Templating\TemplateRendererInterface|\Smarty
     */
    protected function stripeGetRenderer()
    {
        if (method_exists($this, 'getRenderer')) { // mechanism changed in Oxid 6.2
            $bridge = $this->getContainer()->get(TemplateRendererBridgeInterface::class);
            $bridge->setEngine($this->_getSmarty());

            return $bridge->getTemplateRenderer();
        }
        return $this->_getSmarty();
    }

    /**
     * Renders the template with old or current method
     *
     * @param \OxidEsales\EshopCommunity\Internal\Framework\Templating\TemplateRendererInterface|\Smarty $oRenderer
     * @param string $sTemplate
     * @return string
     */
    protected function stripeRenderTemplate($oRenderer, $sTemplate)
    {
        if (method_exists($this, 'getRenderer')) { // mechanism changed in Oxid 6.2
            return $oRenderer->renderTemplate($sTemplate, $this->getViewData());
        }
        return $oRenderer->fetch($sTemplate);
    }

    /**
     * Sends second chance email to customer
     *
     * @param object $oOrder
     * @param string $sFinishPaymentUrl
     * @return bool
     */
    public function stripeSendSecondChanceEmail($oOrder, $sFinishPaymentUrl)
    {
        // shop info
        $shop = $this->_getShop();

        //set mail params (from, fromName, smtp )
        $this->_setMailParams($shop);

        // create messages
        $oRenderer = $this->stripeGetRenderer();

        $subject = Registry::getLang()->translateString('STRIPE_SECOND_CHANCE_MAIL_SUBJECT', null, false) . " Email.php" . $shop->oxshops__oxname->getRawValue() . " (#" . $oOrder->oxorder__oxordernr->value . ")";

        $this->setViewData("order", $oOrder);
        $this->setViewData("shop", $shop);
        $this->setViewData("subject", $subject);
        $this->setViewData("sFinishPaymentUrl", $sFinishPaymentUrl);

        // Process view data array through oxOutput processor
        $this->_processViewArray();

        $oConfig = $this->getConfig();
        $oConfig->setAdminMode(false);

        $this->setBody($this->stripeRenderTemplate($oRenderer, $this->_sStripeSecondChanceTemplate));
        $this->setSubject($subject);

        $oConfig->setAdminMode(true);

        $fullName = $oOrder->oxorder__oxbillfname->getRawValue() . " Email.php" . $oOrder->oxorder__oxbilllname->getRawValue();

        $this->setRecipient($oOrder->oxorder__oxbillemail->value, $fullName);
        $this->setReplyTo($shop->oxshops__oxorderemail->value, $shop->oxshops__oxname->getRawValue());

        if (defined('OXID_PHP_UNIT')) { // don't send email when unittesting
            return true;
        }

        return $this->send();
    }
}
