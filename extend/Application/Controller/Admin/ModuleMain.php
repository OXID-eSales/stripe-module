<?php
/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidSolutionCatalysts\Stripe\extend\Application\Controller\Admin;

class ModuleMain extends ModuleMain_parent
{
    protected $_sStripeNewestVersion = null;

    /**
     * Collects currently newest release version number from github
     *
     * @return string|false
     */
    public function stripeGetNewestReleaseVersion()
    {
        if ($this->_sStripeNewestVersion === null) {
            $this->_sStripeNewestVersion = false;

            $sComposerJson = file_get_contents("https://raw.githubusercontent.com/OXID-eSales/stripe-module/master/composer.json"); // TODO VBFC fix after git push
            if (!empty($sComposerJson)) {
                $aComposerJson = json_decode($sComposerJson, true);
                if (!empty($aComposerJson['version'])) {
                    $this->_sStripeNewestVersion = $aComposerJson['version'];
                }
            }
        }
        return $this->_sStripeNewestVersion;
    }

    /**
     * Returns current version of stripe module
     *
     * @return string|false
     */
    public function stripeGetUsedVersionNumber()
    {
        $sModuleId = $this->stripeGetCurrentModuleId();
        if ($sModuleId) {
            $oModule = oxNew(\OxidEsales\Eshop\Core\Module\Module::class);
            if ($oModule->load($sModuleId)) {
                return $oModule->getInfo('version');
            }
        }
        return false;
    }

    /**
     * Check if stripe module is active
     *
     * @return bool
     */
    public function stripeisModuleActive()
    {
        $sModuleId = $this->stripeGetCurrentModuleId();
        if ($sModuleId) {
            $oModule = oxNew(\OxidEsales\Eshop\Core\Module\Module::class);
            if ($oModule->load($sModuleId)) {
                return $oModule->isActive();
            }
        }
        return false;
    }

    /**
     * Checks if old version warning has to be shown
     *
     * @return bool
     */
    public function stripeShowOldVersionWarning()
    {
        $sNewestVersion = $this->stripeGetNewestReleaseVersion();
        if ($sNewestVersion !== false && version_compare($sNewestVersion, $this->stripeGetUsedVersionNumber(), '>')) {
            return true;
        }
        return false;
    }

    /**
     * Returns currently loaded module id
     *
     * @return string
     */
    protected function stripeGetCurrentModuleId()
    {
        if (\OxidEsales\Eshop\Core\Registry::getRequest()->getRequestParameter("moduleId")) {
            $sModuleId = \OxidEsales\Eshop\Core\Registry::getRequest()->getRequestParameter("moduleId");
        } else {
            $sModuleId = $this->getEditObjectId();
        }
        return $sModuleId;
    }

    /**
     * Executes parent method parent::render(),
     * passes data to Twig engine and returns name of template file "module_main.html.twig".
     *
     * Extension: Return Stripe template if Stripe module was detected
     *
     * @return string
     */
    public function render()
    {
        $sReturn = parent::render();

        if ($this->stripeGetCurrentModuleId() == "stripe" && $this->stripeisModuleActive()) {
            // Return Stripe template
            return "@stripe/stripe_module_main";
        }

        return $sReturn;
    }
}
