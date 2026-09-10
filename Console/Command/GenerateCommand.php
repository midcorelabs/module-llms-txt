<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use MidCore\LlmsTxt\Model\Generator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends Command
{
    public function __construct(
        private State $appState,
        private Generator $generator,
        private LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('midcore:llms:generate');
        $this->setDescription('Generate llms.txt and llms-full.txt into var/llms/{website}/{store}/');
        $this->addOption(
            'store',
            null,
            InputOption::VALUE_REQUIRED,
            'Store view code. Omit to generate every active store view with llms.txt enabled.'
        );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_CRONTAB);
        } catch (\Exception $exception) {
            // Area already set (nested Magento CLI / cron).
        }

        $store = $input->getOption('store');
        $storeCode = is_string($store) && $store !== '' ? $store : null;
        $failed = false;

        try {
            $results = $this->generator->generate($storeCode);
        } catch (LocalizedException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            $this->logger->error($exception->getMessage());
            return Cli::RETURN_FAILURE;
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            return Cli::RETURN_FAILURE;
        }

        foreach ($results as $row) {
            $status = (string)($row['status'] ?? '');
            $line = sprintf(
                '%s llms.txt=%d bytes llms-full.txt=%d bytes (%d ms)',
                $row['store_code'] ?? '',
                (int)($row['llms_bytes'] ?? 0),
                (int)($row['llms_full_bytes'] ?? 0),
                (int)($row['duration_ms'] ?? 0)
            );
            if ($status === 'error') {
                $failed = true;
                $output->writeln('<error>' . $line . ' ' . ($row['error'] ?? '') . '</error>');
                continue;
            }
            $output->writeln('<info>' . $line . '</info>');
        }

        return $failed ? Cli::RETURN_FAILURE : Cli::RETURN_SUCCESS;
    }
}
