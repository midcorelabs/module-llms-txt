<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Cron;

use MidCore\LlmsTxt\Model\Config;
use MidCore\LlmsTxt\Model\Generation\DirtyFlag;
use MidCore\LlmsTxt\Model\Generator;
use Psr\Log\LoggerInterface;

class Generate
{
    public function __construct(
        private Config $config,
        private DirtyFlag $dirtyFlag,
        private Generator $generator,
        private LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isCronEnabled()) {
            return;
        }
        if ($this->config->onlyWhenDirty() && !$this->dirtyFlag->isDirty()) {
            $this->logger->debug('midcore llms cron skipped: not dirty');
            return;
        }
        try {
            $this->generator->generate();
        } catch (\Throwable $exception) {
            $this->logger->error('midcore llms cron failed: ' . $exception->getMessage(), ['exception' => $exception]);
        }
    }
}
