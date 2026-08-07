<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\extend\Core;

use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Domain\Admin\Event\AdminModeChangedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Email extends Email_parent
{
    protected $_sStripeSecondChanceTemplate = '@stripe/stripe_second_chance';

    /**
     * Refund/cancel confirmation template
     *
     * @var string
     */
    protected $_sStripeRefundHtmlTemplate = '@stripe/email/refund_html';
    /**
     * Refund/cancel confirmation template
     *
     * @var string
     */
    protected $_sStripeRefundPlainTemplate = '@stripe/email/refund_plain';
    /**
     * Refund/cancel confirmation template
     *
     * @var string
     */
    protected $_sStripeCancelHtmlTemplate = '@stripe/email/cancel_html';
    /**
     * Refund/cancel confirmation template
     *
     * @var string
     */
    protected $_sStripeCancelPlainTemplate = '@stripe/email/cancel_plain';

    /**
     * Returns the template renderer
     *
     * @return \OxidEsales\EshopCommunity\Internal\Framework\Templating\TemplateRendererInterface
     */
    protected function stripeGetRenderer()
    {
        return $this->getRenderer();
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
        $shop = $this->getShop();

        //set mail params (from, fromName, smtp )
        $this->setMailParams($shop);

        // create messages
        $oRenderer = $this->stripeGetRenderer();

        $subject = Registry::getLang()->translateString('STRIPE_SECOND_CHANCE_MAIL_SUBJECT', null, false)
            . " " . $shop->oxshops__oxname->value
            . " (#" . $oOrder->oxorder__oxordernr->value . ")";

        $this->setViewData("order", $oOrder);
        $this->setViewData("shop", $shop);
        $this->setViewData("subject", $subject);
        $this->setViewData("sFinishPaymentUrl", $sFinishPaymentUrl);

        // Process view data array through oxOutput processor
        $this->processViewArray();

        $blWasAdmin = $this->stripeSwitchAdminMode(false);

        try {
            $this->setBody($this->stripeRenderTemplate($oRenderer, $this->_sStripeSecondChanceTemplate));
            $this->setSubject($subject);
        } finally {
            $this->stripeSwitchAdminMode($blWasAdmin);
        }

        $fullName = $oOrder->oxorder__oxbillfname->value . " " . $oOrder->oxorder__oxbilllname->value;

        $this->setRecipient($oOrder->oxorder__oxbillemail->value, $fullName);
        $this->setReplyTo($shop->oxshops__oxorderemail->value, $shop->oxshops__oxname->value);

        if (defined('OXID_PHP_UNIT')) { // don't send email when unittesting
            return true;
        }

        return $this->send();
    }
    /**
     * Sends the refund confirmation to the customer
     *
     * @param object $oOrder
     * @param float $dRefundedAmount
     * @param string $sCurrency
     * @return bool
     */
    public function stripeSendRefundMailToCustomer($oOrder, $dRefundedAmount, $sCurrency)
    {
        return $this->stripeSendOrderMail(
            $oOrder,
            false,
            $this->_sStripeRefundHtmlTemplate,
            $this->_sStripeRefundPlainTemplate,
            'STRIPE_REFUND_MAIL_SUBJECT',
            $dRefundedAmount,
            $sCurrency
        );
    }

    /**
     * Sends the refund confirmation to the shop owner
     *
     * @param object $oOrder
     * @param float $dRefundedAmount
     * @param string $sCurrency
     * @return bool
     */
    public function stripeSendRefundMailToOwner($oOrder, $dRefundedAmount, $sCurrency)
    {
        return $this->stripeSendOrderMail(
            $oOrder,
            true,
            $this->_sStripeRefundHtmlTemplate,
            $this->_sStripeRefundPlainTemplate,
            'STRIPE_REFUND_MAIL_SUBJECT_OWNER',
            $dRefundedAmount,
            $sCurrency
        );
    }

    /**
     * Sends the cancellation confirmation to the customer
     *
     * @param object $oOrder
     * @param null|float $dRefundedAmount
     * @param string $sCurrency
     * @return bool
     */
    public function stripeSendCancelMailToCustomer($oOrder, $dRefundedAmount, $sCurrency)
    {
        return $this->stripeSendOrderMail(
            $oOrder,
            false,
            $this->_sStripeCancelHtmlTemplate,
            $this->_sStripeCancelPlainTemplate,
            'STRIPE_CANCEL_MAIL_SUBJECT',
            $dRefundedAmount,
            $sCurrency
        );
    }

    /**
     * Sends the cancellation confirmation to the shop owner
     *
     * @param object $oOrder
     * @param null|float $dRefundedAmount
     * @param string $sCurrency
     * @return bool
     */
    public function stripeSendCancelMailToOwner($oOrder, $dRefundedAmount, $sCurrency)
    {
        return $this->stripeSendOrderMail(
            $oOrder,
            true,
            $this->_sStripeCancelHtmlTemplate,
            $this->_sStripeCancelPlainTemplate,
            'STRIPE_CANCEL_MAIL_SUBJECT_OWNER',
            $dRefundedAmount,
            $sCurrency
        );
    }

    /**
     * Renders and sends one confirmation mail. Rendering happens with the admin
     * mode switched off, because these mails are triggered from the backend but
     * use frontend templates - the same handling the second chance mail needs.
     *
     * @param object $oOrder
     * @param bool $blToOwner
     * @param string $sHtmlTemplate
     * @param string $sPlainTemplate
     * @param string $sSubjectIdent
     * @param null|float $dRefundedAmount
     * @param string $sCurrency
     * @return bool
     */
    protected function stripeSendOrderMail(
        $oOrder,
        $blToOwner,
        $sHtmlTemplate,
        $sPlainTemplate,
        $sSubjectIdent,
        $dRefundedAmount,
        $sCurrency
    ) {
        // The customer is written to in the language they ordered in. The shop owner
        // keeps the language the backend is running in, because that copy is read
        // next to the order there - so only the customer mail switches the language.
        // Core\Email::sendSendedNowMail() handles its backend triggered mail the same
        // way, including loading the shop in that language: the shop name and the
        // sender texts are translatable too.
        $iMailLanguage = $blToOwner ? null : $this->stripeOrderLanguage($oOrder);

        $shop = $iMailLanguage === null ? $this->getShop() : $this->getShop($iMailLanguage);
        $this->setMailParams($shop);

        $oRenderer = $this->stripeGetRenderer();

        $oLang = Registry::getLang();
        $iPreviousTplLanguage = (int)$oLang->getTplLanguage();
        $iPreviousBaseLanguage = (int)$oLang->getBaseLanguage();
        if ($iMailLanguage !== null) {
            $oLang->setTplLanguage($iMailLanguage);
            $oLang->setBaseLanguage($iMailLanguage);
        }

        // The subject ident and the templates come from the frontend language files
        // and the frontend theme, so both have to be resolved with the admin mode
        // switched off - these mails are triggered from the backend.
        $blWasAdmin = $this->stripeSwitchAdminMode(false);

        try {
            $sTranslatedSubject = $oLang->translateString($sSubjectIdent, null, false);
            $sSubject = sprintf(
                is_string($sTranslatedSubject) ? $sTranslatedSubject : '',
                $oOrder->oxorder__oxordernr->value
            );

            $this->setViewData("order", $oOrder);
            $this->setViewData("shop", $shop);
            $this->setViewData("subject", $sSubject);
            // the templates format both amounts with the order currency
            $this->setViewData("currency", $oOrder->getOrderCurrency());
            $this->setViewData("stripeRefundedAmount", $dRefundedAmount);
            $this->setViewData("stripeCurrencyCode", $sCurrency);
            $this->setViewData("stripeIsOwnerMail", $blToOwner);

            // Process view data array through oxOutput processor
            $this->processViewArray();

            $this->setBody($this->stripeRenderTemplate($oRenderer, $sHtmlTemplate));
            $this->setAltBody($this->stripeRenderTemplate($oRenderer, $sPlainTemplate));
            $this->setSubject($sSubject);
        } finally {
            // A failing template must not leave the shop behind in frontend mode or
            // in the order language: the admin page that triggered the mail is
            // rendered after this and would lose its templates and translations.
            $this->stripeSwitchAdminMode($blWasAdmin);
            if ($iMailLanguage !== null) {
                $oLang->setTplLanguage($iPreviousTplLanguage);
                $oLang->setBaseLanguage($iPreviousBaseLanguage);
            }
        }

        if ($blToOwner) {
            $this->setRecipient(
                $this->stripeShopFieldAsString($shop, 'oxowneremail'),
                $this->stripeShopFieldAsString($shop, 'oxname')
            );
        } else {
            $fullName = $oOrder->oxorder__oxbillfname->value . " " . $oOrder->oxorder__oxbilllname->value;
            $this->setRecipient($oOrder->oxorder__oxbillemail->value, $fullName);
            $this->setReplyTo(
                $this->stripeShopFieldAsString($shop, 'oxorderemail'),
                $this->stripeShopFieldAsString($shop, 'oxname')
            );
        }

        if (defined('OXID_PHP_UNIT')) { // don't send email when unittesting
            return true;
        }

        return $this->send();
    }

    /**
     * Language the order was placed in. getFieldData() is untyped, so anything
     * that is not a number falls back to the shop default language.
     *
     * @param object $oOrder
     * @return int
     */
    protected function stripeOrderLanguage($oOrder)
    {
        $mLanguage = $oOrder->getFieldData('oxlang');

        return is_numeric($mLanguage) ? (int)$mLanguage : 0;
    }

    /**
     * Switches the admin mode and returns the mode that was active before.
     *
     * Setting the config flag alone is not enough: the template engine resolves
     * its namespaced template directories once and keeps them, so "@__main__"
     * would still point at the admin theme and a frontend template including
     * "email/html/header.html.twig" would not be found. The AdminModeChangedEvent
     * makes the engine reload those directories. The core switches the mode the
     * same way (Core\Email::switchToShopMode()), but its helpers are private,
     * hence the copy here.
     *
     * @param bool $blIsAdmin mode to switch to
     * @return bool mode that was active before
     */
    protected function stripeSwitchAdminMode($blIsAdmin)
    {
        $oConfig = Registry::getConfig();
        $blWasAdmin = (bool)$oConfig->isAdmin();

        if ($blWasAdmin === (bool)$blIsAdmin) {
            return $blWasAdmin;
        }

        $oConfig->setAdminMode($blIsAdmin);

        /** @var EventDispatcherInterface $oEventDispatcher */
        $oEventDispatcher = ContainerFactory::getInstance()
            ->getContainer()
            ->get(EventDispatcherInterface::class);
        $oEventDispatcher->dispatch(new AdminModeChangedEvent());

        return $blWasAdmin;
    }

    /**
     * Shop field as string. getFieldData() is untyped, so anything that is not a
     * plain value yields an empty string instead of being cast.
     *
     * @param \OxidEsales\Eshop\Application\Model\Shop $oShop
     * @param string $sField
     * @return string
     */
    protected function stripeShopFieldAsString($oShop, $sField)
    {
        $mValue = $oShop->getFieldData($sField);

        return is_scalar($mValue) ? (string)$mValue : '';
    }
}
