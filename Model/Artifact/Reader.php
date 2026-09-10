<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model\Artifact;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;

class Reader
{
    private ReadInterface $varDir;

    public function __construct(
        Filesystem $filesystem,
        private PathHelper $pathHelper
    ) {
        $this->varDir = $filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
    }

    public function exists(string $relativePath): bool
    {
        $this->pathHelper->assertWritableRelative($relativePath);
        return $this->varDir->isExist($relativePath) && $this->varDir->isFile($relativePath);
    }

    public function read(string $relativePath): string
    {
        $this->pathHelper->assertWritableRelative($relativePath);
        return $this->varDir->readFile($relativePath);
    }

    /**
     * Weak ETag from mtime + size so we do not hash multi-MB files on every hit.
     */
    public function etag(string $relativePath): string
    {
        $stat = $this->stat($relativePath);
        $mtime = (int)($stat['mtime'] ?? 0);
        $size = (int)($stat['size'] ?? 0);
        return '"' . dechex($mtime) . '-' . dechex($size) . '"';
    }

    public function mtime(string $relativePath): int
    {
        $stat = $this->stat($relativePath);
        return (int)($stat['mtime'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function stat(string $relativePath): array
    {
        $this->pathHelper->assertWritableRelative($relativePath);
        return $this->varDir->stat($relativePath);
    }
}
