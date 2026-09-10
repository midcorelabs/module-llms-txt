<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\Url;
use MidCore\LlmsTxt\Controller\File\Full;
use MidCore\LlmsTxt\Controller\File\Llms;
use MidCore\LlmsTxt\Model\Artifact\PathHelper;

/**
 * Maps /llms.txt and /llms-full.txt onto Magento actions (store resolved from host).
 */
class Router implements RouterInterface
{
    public function __construct(
        private ActionFactory $actionFactory
    ) {
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        $identifier = trim((string)$request->getPathInfo(), '/');
        if (str_starts_with($identifier, 'index.php/')) {
            $identifier = substr($identifier, strlen('index.php/'));
        }

        if ($identifier === PathHelper::LLMS) {
            $request->setModuleName('midcore_llmstxt')
                ->setControllerName('file')
                ->setActionName('llms')
                ->setAlias(Url::REWRITE_REQUEST_PATH_ALIAS, $identifier);
            return $this->actionFactory->create(Llms::class);
        }

        if ($identifier === PathHelper::LLMS_FULL) {
            $request->setModuleName('midcore_llmstxt')
                ->setControllerName('file')
                ->setActionName('full')
                ->setAlias(Url::REWRITE_REQUEST_PATH_ALIAS, $identifier);
            return $this->actionFactory->create(Full::class);
        }

        return null;
    }
}
