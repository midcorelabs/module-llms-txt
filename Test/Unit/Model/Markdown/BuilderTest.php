<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Test\Unit\Model\Markdown;

use MidCore\LlmsTxt\Model\Markdown\Builder;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
    private Builder $builder;

    protected function setUp(): void
    {
        $this->builder = new Builder();
    }

    public function testDocumentMatchesLlmsTxtV2Shape(): void
    {
        $markdown = $this->builder->buildDocument(
            'Acme Shop',
            "Key facts.\nSecond line.",
            ['We sell widgets to professionals.', "## not a heading in details"],
            [
                [
                    'heading' => 'Categories',
                    'links' => [
                        ['name' => 'Widgets', 'url' => 'https://example.com/widgets', 'notes' => 'Top category'],
                    ],
                ],
                [
                    'heading' => 'Products',
                    'links' => [
                        ['name' => 'Red Widget', 'url' => 'https://example.com/red-widget', 'notes' => 'SKU RW-1'],
                    ],
                ],
            ],
            [
                ['name' => 'French store', 'url' => 'https://example.fr/llms.txt', 'notes' => 'fr_FR'],
            ]
        );

        $this->assertStringStartsWith("# Acme Shop\n", $markdown);
        $this->assertStringContainsString("> Key facts.\n> Second line.", $markdown);
        $this->assertStringContainsString("We sell widgets to professionals.", $markdown);
        $this->assertStringContainsString("not a heading in details", $markdown);
        $this->assertStringNotContainsString("## not a heading", $markdown);
        $this->assertStringContainsString("## Categories\n", $markdown);
        $this->assertStringContainsString("- [Widgets](https://example.com/widgets): Top category", $markdown);
        $this->assertStringContainsString("- [Red Widget](https://example.com/red-widget): SKU RW-1", $markdown);
        $this->assertStringContainsString("## Optional\n", $markdown);
        $this->assertStringContainsString("- [French store](https://example.fr/llms.txt): fr_FR", $markdown);
        $this->assertStringEndsWith("\n", $markdown);
    }

    public function testH1IsRequired(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->builder->preamble('  ', null, []);
    }

    public function testEmptySectionsAreOmitted(): void
    {
        $markdown = $this->builder->buildDocument('Site', null, [], [
            ['heading' => 'Products', 'links' => []],
        ]);
        $this->assertSame("# Site\n", $markdown);
        $this->assertStringNotContainsString('## Products', $markdown);
        $this->assertStringNotContainsString('## Optional', $markdown);
    }

    public function testManualMarkdownH1RewrittenToH2(): void
    {
        $chunk = $this->builder->manualMarkdown("# Policy\n\n- [Returns](https://example.com/returns)");
        $this->assertStringStartsWith("## Policy\n", $chunk);
        $this->assertDoesNotMatchRegularExpression('/^# /m', $chunk);
    }

    public function testLinkWithoutNotesHasNoColon(): void
    {
        $line = $this->builder->formatLink('Home', 'https://example.com/');
        $this->assertSame("- [Home](https://example.com/)\n", $line);
    }
}
