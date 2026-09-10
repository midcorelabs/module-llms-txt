<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model\Collector;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Store\Api\Data\StoreInterface;
use MidCore\LlmsTxt\Model\Text\Normalizer;
use MidCore\LlmsTxt\Model\Url\Excluder;

class ProductCollector
{
    public function __construct(
        private CollectionFactory $collectionFactory,
        private StockHelper $stockHelper,
        private Excluder $excluder,
        private Normalizer $normalizer
    ) {
    }

    /**
     * Cursor batch: entity_id > $lastId (no OFFSET).
     *
     * @return LinkItem[]
     */
    public function fetchBatch(StoreInterface $store, int $lastId, int $batchSize, bool $excludeOos): array
    {
        $storeId = (int)$store->getId();
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToSelect(['name', 'sku', 'short_description', 'description', 'url_key']);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->addAttributeToFilter('visibility', ['in' => [
            Visibility::VISIBILITY_IN_CATALOG,
            Visibility::VISIBILITY_IN_SEARCH,
            Visibility::VISIBILITY_BOTH,
        ]]);
        $collection->addAttributeToFilter('entity_id', ['gt' => $lastId]);
        $collection->setOrder('entity_id', 'ASC');
        $collection->addUrlRewrite($storeId);
        $collection->setPageSize($batchSize);
        $collection->setCurPage(1);

        if ($excludeOos) {
            $this->stockHelper->addInStockFilterToCollection($collection);
        }

        $items = [];
        foreach ($collection as $product) {
            $url = (string)$product->getProductUrl();
            if ($this->excluder->isBlocked($url)) {
                continue;
            }
            $description = (string)($product->getShortDescription() ?: $product->getDescription());
            $items[] = new LinkItem(
                (int)$product->getId(),
                (string)$product->getName(),
                $url,
                (string)$product->getSku(),
                $this->normalizer->fromHtml($description, 400)
            );
        }
        $collection->clear();
        return $items;
    }
}
