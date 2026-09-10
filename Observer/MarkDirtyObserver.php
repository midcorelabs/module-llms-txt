<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MidCore\LlmsTxt\Model\Generation\DirtyFlag;

class MarkDirtyObserver implements ObserverInterface
{
    public function __construct(
        private DirtyFlag $dirtyFlag
    ) {
    }

    public function execute(Observer $observer): void
    {
        $this->dirtyFlag->mark();
    }
}
