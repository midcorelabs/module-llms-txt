<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model\Cache;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;

class Invalidator
{
    public const TAG_PREFIX = 'midcore_llmstxt';

    public function __construct(
        private CacheInterface $cache,
        private EventManager $eventManager
    ) {
    }

    public function invalidateStore(int $storeId): void
    {
        $tags = [self::TAG_PREFIX, self::TAG_PREFIX . '_' . $storeId];
        $this->cache->clean($tags);
        $this->eventManager->dispatch('clean_cache_by_tags', [
            'object' => new class ($storeId) implements IdentityInterface {
                public function __construct(private int $storeId)
                {
                }

                public function getIdentities(): array
                {
                    return [
                        Invalidator::TAG_PREFIX,
                        Invalidator::TAG_PREFIX . '_' . $this->storeId,
                    ];
                }
            },
        ]);
    }
}
