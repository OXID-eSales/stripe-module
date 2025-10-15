<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\Service;

use OxidEsales\Eshop\Core\Language;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleConfigurationDaoBridgeInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ModuleSettingBridgeInterface;

/**
 * Service for managing Stripe configuration
 */
class StripeConfigService
{
    /**
     * @var ModuleConfigurationDaoBridgeInterface
     */
    private ModuleConfigurationDaoBridgeInterface $moduleConfigurationBridge;

    /**
     * @var ModuleSettingBridgeInterface
     */
    private ModuleSettingBridgeInterface $moduleSettingBridge;

    /**
     * @var Language
     */
    private Language $language;

    /**
     * Constructor
     *
     * @param ModuleConfigurationDaoBridgeInterface $moduleConfigurationBridge
     * @param ModuleSettingBridgeInterface $moduleSettingBridge
     * @param Language $language
     */
    public function __construct(
        ModuleConfigurationDaoBridgeInterface $moduleConfigurationBridge,
        ModuleSettingBridgeInterface $moduleSettingBridge,
        Language $language
    ) {
        $this->moduleConfigurationBridge = $moduleConfigurationBridge;
        $this->moduleSettingBridge = $moduleSettingBridge;
        $this->language = $language;
    }

    /**
     * Returns configured mode of Stripe (live/test)
     *
     * @return string
     */
    public function getStripeMode(): string
    {
        return (string) $this->getShopConfVar('sStripeMode');
    }

    /**
     * Return Stripe access token for given mode
     *
     * @param string $sMode
     * @return string
     */
    public function getStripeToken(string $sMode = ''): string
    {
        if (empty($sMode)) {
            $sMode = $this->getStripeMode();
        }

        if ($sMode === 'live') {
            return (string) $this->getShopConfVar('sStripeLiveToken');
        } elseif ($sMode === 'test') {
            return (string) $this->getShopConfVar('sStripeTestToken');
        }

        return '';
    }

    /**
     * Return Stripe private key for given mode
     *
     * @param string $sMode
     * @return string
     */
    public function getStripeKey(string $sMode = ''): string
    {
        if (empty($sMode)) {
            $sMode = $this->getStripeMode();
        }

        if ($sMode === 'live') {
            return (string) $this->getShopConfVar('sStripeLiveKey');
        } elseif ($sMode === 'test') {
            return (string) $this->getShopConfVar('sStripeTestKey');
        }

        return '';
    }

    /**
     * Returns Stripe publishable key for given mode
     *
     * @param string $sMode
     * @return string
     */
    public function getPublishableKey(string $sMode = ''): string
    {
        if (empty($sMode)) {
            $sMode = $this->getStripeMode();
        }

        if ($sMode === 'live') {
            return (string) $this->getShopConfVar('sStripeLivePk');
        } elseif ($sMode === 'test') {
            return (string) $this->getShopConfVar('sStripeTestPk');
        }

        return '';
    }

    /**
     * Check if Stripe token is configured
     *
     * @return bool
     */
    public function isTokenConfigured(): bool
    {
        $sMode = $this->getStripeMode();
        return !empty($this->getStripeToken($sMode));
    }

    /**
     * Check if Stripe key is configured
     *
     * @return bool
     */
    public function isKeyConfigured(): bool
    {
        $sMode = $this->getStripeMode();
        return !empty($this->getStripeKey($sMode));
    }

    /**
     * Check if Stripe is fully configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return $this->isTokenConfigured() && $this->isKeyConfigured();
    }

    /**
     * Generates locale string
     * OXID doesn't have a locale logic, so solving it by using the language files
     *
     * @return string
     */
    public function getLocale(): string
    {
        $sLocale = $this->language->translateString('STRIPE_LOCALE');
        if ($this->language->isTranslated() === false) {
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
    public function priceInCent(float $fPrice): int
    {
        return (int) number_format($fPrice * 100, 0, '', '');
    }

    /**
     * Returns config value from module settings
     *
     * @param  string $sVarName
     * @return mixed
     */
    public function getShopConfVar(string $sVarName)
    {
        $moduleConfiguration = $this->moduleConfigurationBridge->get("stripe");
        if (!$moduleConfiguration->hasModuleSetting($sVarName)) {
            return false;
        }
        return $moduleConfiguration->getModuleSetting($sVarName)->getValue();
    }

    /**
     * Save config value to module settings
     *
     * @param string $sVarName
     * @param mixed $mValue
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function saveShopConfVar(string $sVarName, $mValue): void
    {
        Registry::getConfig()->setConfigParam($sVarName, $mValue);
        $this->moduleSettingBridge->save($sVarName, $mValue, 'stripe');
    }
}
