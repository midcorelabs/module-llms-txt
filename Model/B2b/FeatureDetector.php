<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model\B2b;

use Magento\Framework\Module\Manager as ModuleManager;

/**
 * Soft-detect Adobe Commerce B2B. Open Source must install without those modules.
 */
class FeatureDetector
{
    public function __construct(
        private ModuleManager $moduleManager
    ) {
    }

    public function hasSharedCatalog(): bool
    {
        return $this->moduleManager->isEnabled('Magento_SharedCatalog');
    }

    public function hasCompany(): bool
    {
        return $this->moduleManager->isEnabled('Magento_Company');
    }

    public function isB2bPresent(): bool
    {
        return $this->hasSharedCatalog() || $this->hasCompany();
    }

    public function guestVisibilityNote(): ?string
    {
        if (!$this->isB2bPresent()) {
            return null;
        }
        return 'Adobe Commerce B2B modules are present. This file lists products visible in the default/guest catalog context; company or shared-catalog rules may hide additional SKUs from logged-in buyers.';
    }
}
