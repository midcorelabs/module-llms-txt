<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Plugin;

use Magento\Framework\UrlInterface;
use Magento\Robots\Model\Robots;
use Magento\Store\Model\StoreManagerInterface;
use MidCore\LlmsTxt\Model\Config;

class RobotsPlugin
{
    public function __construct(
        private Config $config,
        private StoreManagerInterface $storeManager
    ) {
    }

    public function afterGetData(Robots $subject, string $result): string
    {
        $store = $this->storeManager->getStore();
        $storeId = (int)$store->getId();
        if (!$this->config->isEnabled($storeId) || !$this->config->appendRobots($storeId)) {
            return $result;
        }
        $url = rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_LINK), '/') . '/llms.txt';
        return rtrim($result) . "\n\n# llms.txt (AI agents)\n# " . $url . "\n";
    }
}
