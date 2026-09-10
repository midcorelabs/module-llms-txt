<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model\Artifact;

/**
 * Resolves var/llms/{website_code}/{store_code}/ relative paths.
 * Canonical files are never written under pub/.
 */
class PathHelper
{
    public const ROOT = 'llms';
    public const LLMS = 'llms.txt';
    public const LLMS_FULL = 'llms-full.txt';

    /**
     * Hard cap so llms.txt stays an index even if admin sets 0 (unlimited).
     */
    public const INDEX_SAFETY_CAP = 2000;

    public function directory(string $websiteCode, string $storeCode): string
    {
        return self::ROOT . '/' . $this->safeCode($websiteCode) . '/' . $this->safeCode($storeCode);
    }

    public function llmsTxt(string $websiteCode, string $storeCode): string
    {
        return $this->directory($websiteCode, $storeCode) . '/' . self::LLMS;
    }

    public function llmsFullTxt(string $websiteCode, string $storeCode): string
    {
        return $this->directory($websiteCode, $storeCode) . '/' . self::LLMS_FULL;
    }

    public function relativeFile(string $websiteCode, string $storeCode, string $filename): string
    {
        $filename = $this->normalizeFilename($filename);
        return $this->directory($websiteCode, $storeCode) . '/' . $filename;
    }

    public function normalizeFilename(string $filename): string
    {
        $filename = ltrim($filename, '/');
        if ($filename === self::LLMS || $filename === 'llms') {
            return self::LLMS;
        }
        if ($filename === self::LLMS_FULL || $filename === 'llms-full') {
            return self::LLMS_FULL;
        }
        throw new \InvalidArgumentException('Unknown llms artifact filename.');
    }

    public function assertWritableRelative(string $relativePath): void
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            throw new \InvalidArgumentException('Refusing unsafe artifact path.');
        }
        if (str_starts_with($relativePath, 'pub/') || str_starts_with($relativePath, '/pub/')) {
            throw new \InvalidArgumentException('Canonical llms files must not be written to pub/.');
        }
        if (!str_starts_with($relativePath, self::ROOT . '/')) {
            throw new \InvalidArgumentException('Artifact path must be under var/llms/.');
        }
    }

    public function safeCode(string $code): string
    {
        if ($code === '' || preg_match('/^[A-Za-z0-9_-]+$/', $code) !== 1) {
            throw new \InvalidArgumentException('Unsafe store or website code for artifact path.');
        }
        return $code;
    }
}
