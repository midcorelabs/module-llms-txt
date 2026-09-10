<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model\Artifact;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;

/**
 * Atomic writes (temp + rename) under var/llms/. Never writes to pub/.
 */
class Writer
{
    private WriteInterface $varDir;

    public function __construct(
        Filesystem $filesystem,
        private PathHelper $pathHelper
    ) {
        $this->varDir = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
    }

    /**
     * @param callable(callable(string): void): void $producer Receives an append(string $chunk) callback
     */
    public function atomicStream(string $relativePath, callable $producer): int
    {
        $this->pathHelper->assertWritableRelative($relativePath);
        $tmp = $relativePath . '.tmp.' . bin2hex(random_bytes(6));
        try {
            $this->varDir->writeFile($tmp, '', 'w+');
            $bytes = 0;
            $producer(function (string $chunk) use ($tmp, &$bytes): void {
                if ($chunk === '') {
                    return;
                }
                $this->varDir->writeFile($tmp, $chunk, 'a');
                $bytes += strlen($chunk);
            });
            $this->varDir->renameFile($tmp, $relativePath);
            return $bytes;
        } catch (\Throwable $exception) {
            if ($this->varDir->isExist($tmp)) {
                $this->varDir->delete($tmp);
            }
            throw $exception;
        }
    }

    public function atomicWrite(string $relativePath, string $contents): int
    {
        return $this->atomicStream($relativePath, static function (callable $append) use ($contents): void {
            $append($contents);
        });
    }
}
