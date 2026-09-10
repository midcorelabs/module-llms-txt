<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use MidCore\LlmsTxt\Model\Artifact\PathHelper;
use MidCore\LlmsTxt\Model\Artifact\Writer;
use MidCore\LlmsTxt\Model\B2b\FeatureDetector;
use MidCore\LlmsTxt\Model\Cache\Invalidator;
use MidCore\LlmsTxt\Model\Collector\CategoryCollector;
use MidCore\LlmsTxt\Model\Collector\CmsPageCollector;
use MidCore\LlmsTxt\Model\Collector\LinkItem;
use MidCore\LlmsTxt\Model\Collector\ProductCollector;
use MidCore\LlmsTxt\Model\Generation\DirtyFlag;
use MidCore\LlmsTxt\Model\Generation\StatusRepository;
use MidCore\LlmsTxt\Model\Markdown\Builder;
use MidCore\LlmsTxt\Model\Store\SiblingResolver;
use Psr\Log\LoggerInterface;

class Generator
{
    public function __construct(
        private StoreManagerInterface $storeManager,
        private Emulation $emulation,
        private Config $config,
        private PathHelper $pathHelper,
        private Writer $writer,
        private Builder $builder,
        private CategoryCollector $categoryCollector,
        private ProductCollector $productCollector,
        private CmsPageCollector $cmsPageCollector,
        private SiblingResolver $siblingResolver,
        private FeatureDetector $b2b,
        private StatusRepository $statusRepository,
        private DirtyFlag $dirtyFlag,
        private Invalidator $invalidator,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param string|null $storeCode Null generates every active store view that is enabled in config
     * @return array<int, array<string, mixed>>
     */
    public function generate(?string $storeCode = null): array
    {
        $single = $storeCode !== null && $storeCode !== '';
        $results = [];
        $failed = false;
        foreach ($this->resolveStores($storeCode) as $store) {
            try {
                $results[] = $this->generateForStore($store);
            } catch (\Throwable $exception) {
                $failed = true;
                $this->logger->error(
                    sprintf('llms generate failed for store %s: %s', $store->getCode(), $exception->getMessage()),
                    ['exception' => $exception]
                );
                $payload = [
                    'status' => 'error',
                    'store_code' => (string)$store->getCode(),
                    'website_code' => $this->websiteCode($store),
                    'generated_at' => date('c'),
                    'duration_ms' => 0,
                    'llms_bytes' => 0,
                    'llms_full_bytes' => 0,
                    'error' => $exception->getMessage(),
                ];
                $this->statusRepository->save((int)$store->getId(), $payload);
                if ($single) {
                    throw $exception;
                }
                $results[] = $payload;
            }
        }
        if (!$failed) {
            $this->dirtyFlag->clear();
        }
        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function generateForStore(StoreInterface $store): array
    {
        $storeId = (int)$store->getId();
        if (!$this->config->isEnabled($storeId)) {
            throw new LocalizedException(
                __('llms.txt is disabled for store "%1". Enable it under Stores > Configuration > MidCore > llms.txt.', $store->getCode())
            );
        }

        $websiteCode = $this->websiteCode($store);
        $storeCode = (string)$store->getCode();
        $started = microtime(true);
        $this->logger->info(sprintf('Generating llms artifacts for store %s', $storeCode));

        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        try {
            $indexBytes = $this->writeArtifact($store, $this->pathHelper->llmsTxt($websiteCode, $storeCode), false);
            $fullBytes = 0;
            if ($this->config->includeLlmsFull($storeId)) {
                $fullBytes = $this->writeArtifact($store, $this->pathHelper->llmsFullTxt($websiteCode, $storeCode), true);
            }
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }

        $payload = [
            'status' => 'success',
            'store_code' => $storeCode,
            'website_code' => $websiteCode,
            'generated_at' => date('c'),
            'duration_ms' => (int)round((microtime(true) - $started) * 1000),
            'llms_bytes' => $indexBytes,
            'llms_full_bytes' => $fullBytes,
            'error' => '',
        ];
        $this->statusRepository->save($storeId, $payload);
        $this->invalidator->invalidateStore($storeId);
        $this->logger->info(sprintf(
            'Generated llms artifacts for store %s (%d + %d bytes)',
            $storeCode,
            $indexBytes,
            $fullBytes
        ));
        return $payload;
    }

    /**
     * @return StoreInterface[]
     */
    private function resolveStores(?string $storeCode): array
    {
        if ($storeCode !== null && $storeCode !== '') {
            $store = $this->storeManager->getStore($storeCode);
            if (!$store->getId() || $store->getCode() === 'admin') {
                throw new LocalizedException(__('Unknown store view code: %1', $storeCode));
            }
            return [$store];
        }

        $stores = [];
        foreach ($this->storeManager->getStores() as $store) {
            if (!$store->isActive()) {
                continue;
            }
            if (!$this->config->isEnabled((int)$store->getId())) {
                continue;
            }
            $stores[] = $store;
        }
        if ($stores === []) {
            throw new LocalizedException(__('No enabled store views found for llms.txt generation.'));
        }
        return $stores;
    }

    private function writeArtifact(StoreInterface $store, string $relativePath, bool $full): int
    {
        $storeId = (int)$store->getId();
        $batchSize = $this->config->getBatchSize();
        $title = $this->resolveTitle($store);
        $summary = $this->resolveSummary($store, $full);
        $details = $this->resolveDetails($store, $full);

        return $this->writer->atomicStream($relativePath, function (callable $append) use ($store, $storeId, $batchSize, $full, $title, $summary, $details): void {
            $append($this->builder->preamble($title, $summary, $details));

            if ($this->config->includeCategories($storeId)) {
                $this->writeSection(
                    $append,
                    'Categories',
                    function (int $lastId) use ($store, $batchSize): array {
                        return $this->categoryCollector->fetchBatch($store, $lastId, $batchSize);
                    },
                    fn (LinkItem $item): string => $this->builder->formatLink($item->name, $item->url, $item->description),
                    $full ? 0 : $this->indexCap($this->config->getMaxCategoriesIndex($storeId))
                );
            }

            if ($this->config->includeProducts($storeId)) {
                $excludeOos = $this->config->excludeOutOfStock($storeId);
                $this->writeSection(
                    $append,
                    'Products',
                    function (int $lastId) use ($store, $batchSize, $excludeOos): array {
                        return $this->productCollector->fetchBatch($store, $lastId, $batchSize, $excludeOos);
                    },
                    function (LinkItem $item) use ($full): string {
                        $notes = $this->productNotes($item, $full);
                        return $this->builder->formatLink($item->name, $item->url, $notes);
                    },
                    $full ? 0 : $this->indexCap($this->config->getMaxProductsIndex($storeId))
                );
            }

            if ($this->config->includeCms($storeId)) {
                $restrict = $this->config->getCmsPageIds($storeId);
                $this->writeSection(
                    $append,
                    'Pages',
                    function (int $lastId) use ($store, $batchSize, $restrict): array {
                        return $this->cmsPageCollector->fetchBatch($store, $lastId, $batchSize, $restrict);
                    },
                    fn (LinkItem $item): string => $this->builder->formatLink($item->name, $item->url, $item->sku),
                    $full ? 0 : $this->indexCap(200)
                );
            }

            $append($this->builder->manualMarkdown($this->config->getManualMarkdown($storeId)));

            $optional = [];
            if (!$full && $this->config->includeLlmsFull($storeId)) {
                $optional[] = [
                    'name' => 'Full catalog',
                    'url' => $this->storeFileUrl($store, PathHelper::LLMS_FULL),
                    'notes' => 'Complete product listing with SKUs and short descriptions',
                ];
            }
            if ($full) {
                $optional[] = [
                    'name' => 'Catalog index',
                    'url' => $this->storeFileUrl($store, PathHelper::LLMS),
                    'notes' => 'Curated llms.txt index',
                ];
            }
            if ($this->config->includeSiblingStores($storeId)) {
                foreach ($this->siblingResolver->siblings($store) as $sibling) {
                    $optional[] = $sibling;
                }
            }
            $append($this->builder->optionalSection($optional));
        });
    }

    /**
     * @param callable(int): LinkItem[] $fetchBatch
     * @param callable(LinkItem): string $formatter
     */
    private function writeSection(
        callable $append,
        string $heading,
        callable $fetchBatch,
        callable $formatter,
        int $limit
    ): void {
        $lastId = 0;
        $written = 0;
        $opened = false;
        do {
            $items = $fetchBatch($lastId);
            if ($items === []) {
                break;
            }
            foreach ($items as $item) {
                $lastId = $item->id;
                if ($limit > 0 && $written >= $limit) {
                    return;
                }
                if (!$opened) {
                    $append($this->builder->sectionHeading($heading));
                    $opened = true;
                }
                $append($formatter($item));
                $written++;
            }
            $count = count($items);
            $items = null;
        } while ($count > 0 && ($limit === 0 || $written < $limit));

        if ($opened) {
            $append("\n");
        }
    }

    private function indexCap(int $configured): int
    {
        if ($configured <= 0) {
            return PathHelper::INDEX_SAFETY_CAP;
        }
        return min($configured, PathHelper::INDEX_SAFETY_CAP);
    }

    private function productNotes(LinkItem $item, bool $full): string
    {
        $parts = [];
        if ($item->sku !== '') {
            $parts[] = 'SKU ' . $item->sku;
        }
        if ($item->description !== '') {
            $parts[] = $item->description;
        }
        $notes = implode('. ', $parts);
        if (!$full && mb_strlen($notes) > 160) {
            $notes = rtrim(mb_substr($notes, 0, 159)) . '…';
        }
        return $notes;
    }

    private function resolveTitle(StoreInterface $store): string
    {
        $storeId = (int)$store->getId();
        $override = $this->config->getSiteNameOverride($storeId);
        if ($override !== '') {
            return $override;
        }
        $info = trim((string)$store->getConfig('general/store_information/name'));
        if ($info !== '') {
            return $info;
        }
        return (string)$store->getFrontendName();
    }

    private function resolveSummary(StoreInterface $store, bool $full): ?string
    {
        $storeId = (int)$store->getId();
        if (!$this->config->includeIdentity($storeId)) {
            return $full ? 'Full catalog listing for AI agents.' : null;
        }
        $summary = $this->config->getSummary($storeId);
        if ($summary !== '') {
            return $summary;
        }
        $name = $this->resolveTitle($store);
        if ($full) {
            return $name . ' full catalog (names, SKUs, URLs, short descriptions) for AI agents.';
        }
        return $name . ' Magento store catalog index for AI agents.';
    }

    /**
     * @return string[]
     */
    private function resolveDetails(StoreInterface $store, bool $full): array
    {
        $storeId = (int)$store->getId();
        $paragraphs = [];
        if ($this->config->includeIdentity($storeId)) {
            $raw = $this->config->getDetails($storeId);
            if ($raw !== '') {
                foreach (preg_split('/\n\s*\n/', $raw) ?: [] as $chunk) {
                    $chunk = trim($chunk);
                    if ($chunk !== '') {
                        $paragraphs[] = $chunk;
                    }
                }
            }
        }
        if ($full) {
            $paragraphs[] = 'This is the fuller catalog listing. A curated index is at ' . $this->storeFileUrl($store, PathHelper::LLMS) . '.';
        }
        $b2b = $this->b2b->guestVisibilityNote();
        if ($b2b !== null) {
            $paragraphs[] = $b2b;
        }
        return $paragraphs;
    }

    private function storeFileUrl(StoreInterface $store, string $filename): string
    {
        return rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_LINK), '/') . '/' . $filename;
    }

    private function websiteCode(StoreInterface $store): string
    {
        return (string)$this->storeManager->getWebsite($store->getWebsiteId())->getCode();
    }
}
