<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model\Serve;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Model\StoreManagerInterface;
use MidCore\LlmsTxt\Model\Artifact\PathHelper;
use MidCore\LlmsTxt\Model\Artifact\Reader;
use MidCore\LlmsTxt\Model\Cache\Invalidator;
use MidCore\LlmsTxt\Model\Cache\OriginMissCounter;
use MidCore\LlmsTxt\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Serves pre-generated artifacts. Never generates on the request path.
 */
class FileServer
{
    public function __construct(
        private RequestInterface $request,
        private RawFactory $rawFactory,
        private StoreManagerInterface $storeManager,
        private Config $config,
        private PathHelper $pathHelper,
        private Reader $reader,
        private OriginMissCounter $originMissCounter,
        private LoggerInterface $logger
    ) {
    }

    public function serve(string $filename): Raw
    {
        $result = $this->rawFactory->create();
        $store = $this->storeManager->getStore();
        $storeId = (int)$store->getId();
        $filename = $this->pathHelper->normalizeFilename($filename);

        if (!$this->config->isEnabled($storeId)) {
            return $this->notFound($result);
        }
        if ($filename === PathHelper::LLMS_FULL && !$this->config->includeLlmsFull($storeId)) {
            return $this->notFound($result);
        }

        try {
            $websiteCode = (string)$this->storeManager->getWebsite($store->getWebsiteId())->getCode();
            $relative = $this->pathHelper->relativeFile($websiteCode, (string)$store->getCode(), $filename);
        } catch (\InvalidArgumentException $exception) {
            $this->logger->warning('llms serve rejected path: ' . $exception->getMessage());
            return $this->notFound($result);
        }

        if (!$this->reader->exists($relative)) {
            $this->originMissCounter->increment($storeId);
            $this->logger->info(sprintf(
                'llms origin miss (partial counter) store=%s file=%s',
                $store->getCode(),
                $filename
            ));
            return $this->notFound($result);
        }

        $etag = $this->reader->etag($relative);
        $mtime = $this->reader->mtime($relative);
        $lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
        $ttl = $this->config->getCacheTtl($storeId);
        $this->applyCacheHeaders($result, $filename, $storeId, $etag, $lastModified, $ttl);

        $ifNoneMatch = trim((string)$this->request->getHeader('If-None-Match'));
        if ($ifNoneMatch !== '' && $this->etagMatches($ifNoneMatch, $etag)) {
            $result->setHttpResponseCode(304);
            return $result;
        }

        $ifModifiedSince = trim((string)$this->request->getHeader('If-Modified-Since'));
        if ($ifModifiedSince !== '' && strtotime($ifModifiedSince) >= $mtime) {
            $result->setHttpResponseCode(304);
            return $result;
        }

        $result->setContents($this->reader->read($relative));
        return $result;
    }

    private function applyCacheHeaders(
        Raw $result,
        string $filename,
        int $storeId,
        string $etag,
        string $lastModified,
        int $ttl
    ): void {
        $result->setHeader('Content-Type', 'text/markdown; charset=UTF-8', true);
        $result->setHeader('X-Content-Type-Options', 'nosniff', true);
        $result->setHeader('ETag', $etag, true);
        $result->setHeader('Last-Modified', $lastModified, true);
        $result->setHeader('X-Magento-Tags', Invalidator::TAG_PREFIX . ',' . Invalidator::TAG_PREFIX . '_' . $storeId, true);
        $result->setHeader('X-MidCore-Llms-File', $filename, true);
        if ($ttl > 0) {
            $result->setHeader('Cache-Control', sprintf('public, max-age=%d, s-maxage=%d', $ttl, $ttl), true);
            $result->setHeader('Pragma', 'cache', true);
        } else {
            $result->setHeader('Cache-Control', 'public, max-age=0, s-maxage=0, must-revalidate', true);
        }
    }

    private function etagMatches(string $header, string $etag): bool
    {
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 'W/')) {
                $part = trim(substr($part, 2));
            }
            if ($part === $etag || $part === '*') {
                return true;
            }
        }
        return false;
    }

    private function notFound(Raw $result): Raw
    {
        $result->setHttpResponseCode(404);
        $result->setHeader('Content-Type', 'text/plain; charset=UTF-8', true);
        $result->setHeader('Cache-Control', 'no-store', true);
        $result->setContents("Not Found\n");
        return $result;
    }
}
