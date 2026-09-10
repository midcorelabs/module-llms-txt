<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Controller\File;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpHeadActionInterface;
use Magento\Framework\Controller\ResultInterface;
use MidCore\LlmsTxt\Model\Artifact\PathHelper;
use MidCore\LlmsTxt\Model\Serve\FileServer;

class Full implements HttpGetActionInterface, HttpHeadActionInterface
{
    public function __construct(
        private FileServer $fileServer
    ) {
    }

    public function execute(): ResultInterface
    {
        return $this->fileServer->serve(PathHelper::LLMS_FULL);
    }
}
