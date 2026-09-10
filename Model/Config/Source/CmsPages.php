<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model\Config\Source;

use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class CmsPages implements OptionSourceInterface
{
    public function __construct(
        private CollectionFactory $collectionFactory
    ) {
    }

    public function toOptionArray(): array
    {
        $options = [];
        $collection = $this->collectionFactory->create();
        $collection->setOrder('title', 'ASC');
        foreach ($collection as $page) {
            $options[] = [
                'value' => (int)$page->getId(),
                'label' => sprintf('%s (%s)', $page->getTitle(), $page->getIdentifier()),
            ];
        }
        return $options;
    }
}
