<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model\Generation;

use Magento\Framework\FlagManager;

class StatusRepository
{
    public const FLAG_CODE = 'midcore_llmstxt_status';

    public function __construct(
        private FlagManager $flagManager
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function save(int $storeId, array $payload): void
    {
        $all = $this->getAll();
        $all[(string)$storeId] = $payload;
        $this->flagManager->saveFlag(self::FLAG_CODE, $all);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(int $storeId): ?array
    {
        $all = $this->getAll();
        $row = $all[(string)$storeId] ?? null;
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAll(): array
    {
        $data = $this->flagManager->getFlagData(self::FLAG_CODE);
        return is_array($data) ? $data : [];
    }
}
