<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model\Collector;

use Magento\Cms\Helper\Page as CmsPageHelper;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use MidCore\LlmsTxt\Model\Url\Excluder;

class CmsPageCollector
{
    public function __construct(
        private CollectionFactory $collectionFactory,
        private CmsPageHelper $cmsPageHelper,
        private Excluder $excluder
    ) {
    }

    /**
     * @param int[] $restrictToIds Empty means all enabled pages for the store
     * @return LinkItem[]
     */
    public function fetchBatch(StoreInterface $store, int $lastId, int $batchSize, array $restrictToIds): array
    {
        $storeId = (int)$store->getId();
        $collection = $this->collectionFactory->create();
        $collection->addStoreFilter($storeId);
        $collection->addFieldToFilter('is_active', 1);
        $collection->addFieldToFilter('page_id', ['gt' => $lastId]);
        if ($restrictToIds !== []) {
            $collection->addFieldToFilter('page_id', ['in' => $restrictToIds]);
        }
        $collection->setOrder('page_id', 'ASC');
        $collection->setPageSize($batchSize);
        $collection->setCurPage(1);

        $items = [];
        foreach ($collection as $page) {
            $identifier = (string)$page->getIdentifier();
            if ($this->excluder->isBlockedIdentifier($identifier)) {
                continue;
            }
            $url = (string)$this->cmsPageHelper->getPageUrl($page->getId());
            if ($url === '' || $this->excluder->isBlocked($url)) {
                continue;
            }
            $items[] = new LinkItem(
                (int)$page->getId(),
                (string)$page->getTitle(),
                $url,
                $identifier
            );
        }
        $collection->clear();
        return $items;
    }
}
