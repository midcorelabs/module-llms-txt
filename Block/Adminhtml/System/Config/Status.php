<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\StoreManagerInterface;
use MidCore\LlmsTxt\Model\Cache\OriginMissCounter;
use MidCore\LlmsTxt\Model\Generation\DirtyFlag;
use MidCore\LlmsTxt\Model\Generation\StatusRepository;

class Status extends Field
{
    public function __construct(
        Context $context,
        private StoreManagerInterface $storeManager,
        private StatusRepository $statusRepository,
        private OriginMissCounter $originMissCounter,
        private DirtyFlag $dirtyFlag,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $lines = [];
        $lines[] = 'Dirty flag: ' . ($this->dirtyFlag->isDirty() ? 'yes (pending regenerate)' : 'no');
        foreach ($this->storeManager->getStores() as $store) {
            if (!$store->isActive()) {
                continue;
            }
            $storeId = (int)$store->getId();
            $row = $this->statusRepository->get($storeId);
            $miss = $this->originMissCounter->get($storeId);
            if ($row === null) {
                $lines[] = sprintf(
                    '%s (%s): never generated. Origin misses (partial): %d',
                    $store->getName(),
                    $store->getCode(),
                    $miss
                );
                continue;
            }
            $lines[] = sprintf(
                '%s (%s): %s at %s; llms.txt %d bytes; llms-full.txt %d bytes; origin misses (partial): %d%s',
                $store->getName(),
                $store->getCode(),
                (string)($row['status'] ?? ''),
                (string)($row['generated_at'] ?? ''),
                (int)($row['llms_bytes'] ?? 0),
                (int)($row['llms_full_bytes'] ?? 0),
                $miss,
                !empty($row['error']) ? ' error: ' . $row['error'] : ''
            );
        }
        $html = '<div class="midcore-llmstxt-status" style="white-space:pre-wrap;line-height:1.5">';
        $html .= $this->escapeHtml(implode("\n", $lines));
        $html .= '</div>';
        return $html;
    }
}
