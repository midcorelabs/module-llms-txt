<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Test\Unit\Model\Text;

use MidCore\LlmsTxt\Model\Text\Normalizer;
use PHPUnit\Framework\TestCase;

class NormalizerTest extends TestCase
{
    public function testStripsTagsAndTruncates(): void
    {
        $normalizer = new Normalizer();
        $this->assertSame(
            'Hello & world',
            $normalizer->fromHtml('<p>Hello &amp; <strong>world</strong></p>', 80)
        );
        $truncated = $normalizer->fromHtml('<p>' . str_repeat('widget ', 40) . '</p>', 20);
        $this->assertStringEndsWith('…', $truncated);
        $this->assertTrue(mb_strlen($truncated) <= 20);
    }
}
