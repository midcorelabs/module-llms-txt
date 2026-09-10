<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model\Cache;

use Magento\Framework\App\CacheInterface;

/**
 * Origin-only miss counter. Partial: Fastly/CDN hits never increment this.
 * No database write.
 */
class OriginMissCounter
{
    private const KEY_PREFIX = 'midcore_llmstxt_origin_miss_';
    private const TTL = 2592000;

    /**
     * Separate from Invalidator::TAG_PREFIX so regenerate / Fastly purge
     * of /llms.txt does not reset the partial miss counter.
     */
    public const CACHE_TAG = 'midcore_llmstxt_analytics';

    public function __construct(
        private CacheInterface $cache
    ) {
    }

    public function increment(int $storeId): void
    {
        $key = self::KEY_PREFIX . $storeId;
        $n = (int)$this->cache->load($key);
        $this->cache->save((string)($n + 1), $key, [self::CACHE_TAG], self::TTL);
    }

    public function get(int $storeId): int
    {
        return (int)$this->cache->load(self::KEY_PREFIX . $storeId);
    }
}
