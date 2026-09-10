<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model\Store;

use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class SiblingResolver
{
    public function __construct(
        private StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Other active store views on the same website.
     *
     * @return array<int, array{name: string, url: string, notes: string}>
     */
    public function siblings(StoreInterface $store): array
    {
        $currentId = (int)$store->getId();
        $websiteId = (int)$store->getWebsiteId();
        $links = [];
        foreach ($this->storeManager->getStores() as $candidate) {
            if (!$candidate->isActive()) {
                continue;
            }
            if ((int)$candidate->getId() === $currentId) {
                continue;
            }
            if ((int)$candidate->getWebsiteId() !== $websiteId) {
                continue;
            }
            $base = rtrim($candidate->getBaseUrl(UrlInterface::URL_TYPE_LINK), '/');
            $locale = (string)$candidate->getConfig('general/locale/code');
            $notes = $locale !== '' ? $locale : 'Alternate store view';
            $links[] = [
                'name' => (string)$candidate->getFrontendName(),
                'url' => $base . '/llms.txt',
                'notes' => $notes,
            ];
        }
        return $links;
    }
}
