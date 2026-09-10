<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Test\Unit\Model\Artifact;

use MidCore\LlmsTxt\Model\Artifact\PathHelper;
use PHPUnit\Framework\TestCase;

class PathHelperTest extends TestCase
{
    private PathHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new PathHelper();
    }

    public function testRelativePathsStayUnderVarLlms(): void
    {
        $this->assertSame('llms/base/default/llms.txt', $this->helper->llmsTxt('base', 'default'));
        $this->assertSame('llms/base/en/llms-full.txt', $this->helper->llmsFullTxt('base', 'en'));
        $this->assertStringStartsWith('llms/', $this->helper->directory('base', 'default'));
        $this->assertStringNotContainsString('pub/', $this->helper->llmsTxt('base', 'default'));
    }

    public function testRejectsPathTraversalInCodes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->helper->llmsTxt('../pub', 'default');
    }

    public function testRejectsEmptyAndUnsafeCodes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->helper->safeCode('base/default');
    }

    public function testAssertWritableRelativeBlocksPub(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->helper->assertWritableRelative('pub/llms.txt');
    }

    public function testAssertWritableRelativeBlocksDotDot(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->helper->assertWritableRelative('llms/../pub/llms.txt');
    }

    public function testNormalizeFilename(): void
    {
        $this->assertSame('llms.txt', $this->helper->normalizeFilename('llms.txt'));
        $this->assertSame('llms-full.txt', $this->helper->normalizeFilename('llms-full'));
    }

    public function testUnknownFilenameRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->helper->normalizeFilename('robots.txt');
    }
}
