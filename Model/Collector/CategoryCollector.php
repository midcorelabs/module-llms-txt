<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model\Collector;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use MidCore\LlmsTxt\Model\Text\Normalizer;
use MidCore\LlmsTxt\Model\Url\Excluder;

class CategoryCollector
{
    public function __construct(
        private CollectionFactory $collectionFactory,
        private StoreManagerInterface $storeManager,
        private Excluder $excluder,
        private Normalizer $normalizer
    ) {
    }

    /**
     * @return LinkItem[]
     */
    public function fetchBatch(StoreInterface $store, int $lastId, int $batchSize): array
    {
        $storeId = (int)$store->getId();
        $rootId = (int)$this->storeManager->getGroup($store->getStoreGroupId())->getRootCategoryId();
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect(['name', 'description', 'url_key']);
        $collection->addAttributeToFilter('is_active', 1);
        $collection->addAttributeToFilter('entity_id', ['gt' => $lastId]);
        $collection->addAttributeToFilter('level', ['gt' => 1]);
        if ($rootId > 0) {
            $collection->addAttributeToFilter('entity_id', ['neq' => $rootId]);
        }
        $collection->addUrlRewriteToResult();
        $collection->setOrder('entity_id', 'ASC');
        $collection->setPageSize($batchSize);
        $collection->setCurPage(1);

        $items = [];
        foreach ($collection as $category) {
            $url = (string)$category->getUrl();
            if ($url === '' || $this->excluder->isBlocked($url)) {
                continue;
            }
            $items[] = new LinkItem(
                (int)$category->getId(),
                (string)$category->getName(),
                $url,
                '',
                $this->normalizer->fromHtml((string)$category->getDescription(), 200)
            );
        }
        $collection->clear();
        return $items;
    }
}
