<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_PATH_ENABLED = 'midcore_llmstxt/general/enabled';
    public const XML_PATH_INCLUDE_LLMS_FULL = 'midcore_llmstxt/general/include_llms_full';
    public const XML_PATH_APPEND_ROBOTS = 'midcore_llmstxt/general/append_robots';
    public const XML_PATH_CACHE_TTL = 'midcore_llmstxt/general/cache_ttl';

    public const XML_PATH_INCLUDE_IDENTITY = 'midcore_llmstxt/content/include_identity';
    public const XML_PATH_SITE_NAME = 'midcore_llmstxt/content/site_name';
    public const XML_PATH_SUMMARY = 'midcore_llmstxt/content/summary';
    public const XML_PATH_DETAILS = 'midcore_llmstxt/content/details';
    public const XML_PATH_INCLUDE_CATEGORIES = 'midcore_llmstxt/content/include_categories';
    public const XML_PATH_MAX_CATEGORIES_INDEX = 'midcore_llmstxt/content/max_categories_index';
    public const XML_PATH_INCLUDE_PRODUCTS = 'midcore_llmstxt/content/include_products';
    public const XML_PATH_EXCLUDE_OOS = 'midcore_llmstxt/content/exclude_oos';
    public const XML_PATH_MAX_PRODUCTS_INDEX = 'midcore_llmstxt/content/max_products_index';
    public const XML_PATH_INCLUDE_CMS = 'midcore_llmstxt/content/include_cms';
    public const XML_PATH_CMS_PAGE_IDS = 'midcore_llmstxt/content/cms_page_ids';
    public const XML_PATH_MANUAL_MARKDOWN = 'midcore_llmstxt/content/manual_markdown';
    public const XML_PATH_INCLUDE_SIBLINGS = 'midcore_llmstxt/content/include_sibling_stores';

    public const XML_PATH_CRON_ENABLED = 'midcore_llmstxt/generation/cron_enabled';
    public const XML_PATH_CRON_EXPR = 'midcore_llmstxt/generation/cron_expr';
    public const XML_PATH_ONLY_WHEN_DIRTY = 'midcore_llmstxt/generation/only_when_dirty';
    public const XML_PATH_BATCH_SIZE = 'midcore_llmstxt/generation/batch_size';

    public function __construct(
        private ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function includeLlmsFull(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_INCLUDE_LLMS_FULL, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function appendRobots(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_APPEND_ROBOTS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getCacheTtl(?int $storeId = null): int
    {
        $ttl = (int)$this->scopeConfig->getValue(self::XML_PATH_CACHE_TTL, ScopeInterface::SCOPE_STORE, $storeId);
        return max(0, $ttl);
    }

    public function includeIdentity(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_INCLUDE_IDENTITY, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getSiteNameOverride(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(self::XML_PATH_SITE_NAME, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getSummary(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(self::XML_PATH_SUMMARY, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getDetails(?int $storeId = null): string
    {
        return trim((string)$this->scopeConfig->getValue(self::XML_PATH_DETAILS, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function includeCategories(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_INCLUDE_CATEGORIES, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getMaxCategoriesIndex(?int $storeId = null): int
    {
        return max(0, (int)$this->scopeConfig->getValue(
            self::XML_PATH_MAX_CATEGORIES_INDEX,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function includeProducts(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_INCLUDE_PRODUCTS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function excludeOutOfStock(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_EXCLUDE_OOS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getMaxProductsIndex(?int $storeId = null): int
    {
        return max(0, (int)$this->scopeConfig->getValue(
            self::XML_PATH_MAX_PRODUCTS_INDEX,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    public function includeCms(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_INCLUDE_CMS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * @return int[]
     */
    public function getCmsPageIds(?int $storeId = null): array
    {
        $raw = (string)$this->scopeConfig->getValue(self::XML_PATH_CMS_PAGE_IDS, ScopeInterface::SCOPE_STORE, $storeId);
        if (trim($raw) === '') {
            return [];
        }
        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $id = (int)trim($part);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    public function getManualMarkdown(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_MANUAL_MARKDOWN, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function includeSiblingStores(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_INCLUDE_SIBLINGS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isCronEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_CRON_ENABLED);
    }

    public function onlyWhenDirty(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ONLY_WHEN_DIRTY);
    }

    public function getBatchSize(): int
    {
        $size = (int)$this->scopeConfig->getValue(self::XML_PATH_BATCH_SIZE);
        return max(1, $size);
    }
}
