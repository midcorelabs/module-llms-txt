<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model\Generation;

use Magento\Framework\FlagManager;

class DirtyFlag
{
    public const FLAG_CODE = 'midcore_llmstxt_dirty';

    public function __construct(
        private FlagManager $flagManager
    ) {
    }

    public function mark(): void
    {
        $this->flagManager->saveFlag(self::FLAG_CODE, [
            'dirty' => 1,
            'at' => time(),
        ]);
    }

    public function isDirty(): bool
    {
        $data = $this->flagManager->getFlagData(self::FLAG_CODE);
        return is_array($data) && !empty($data['dirty']);
    }

    public function clear(): void
    {
        $this->flagManager->saveFlag(self::FLAG_CODE, [
            'dirty' => 0,
            'at' => time(),
        ]);
    }
}
