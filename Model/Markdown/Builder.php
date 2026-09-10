<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model\Markdown;

/**
 * llmstxt.org v2 markdown builder. Pure PHP so it can be unit-tested without Magento.
 */
class Builder
{
    public function preamble(string $title, ?string $summary, array $detailParagraphs): string
    {
        $title = $this->singleLine($title);
        if ($title === '') {
            throw new \InvalidArgumentException('llms.txt requires an H1 site name.');
        }

        $parts = ['# ' . $title, ''];

        if ($summary !== null && trim($summary) !== '') {
            $parts[] = $this->blockquote($summary);
            $parts[] = '';
        }

        foreach ($detailParagraphs as $paragraph) {
            $paragraph = $this->stripHeadings(trim((string)$paragraph));
            if ($paragraph === '') {
                continue;
            }
            $parts[] = $paragraph;
            $parts[] = '';
        }

        return implode("\n", $parts);
    }

    public function sectionHeading(string $heading): string
    {
        $heading = $this->singleLine($heading);
        if ($heading === '') {
            throw new \InvalidArgumentException('Section heading must not be empty.');
        }
        return '## ' . $heading . "\n\n";
    }

    public function formatLink(string $name, string $url, string $notes = ''): string
    {
        $name = $this->escapeLinkText($this->singleLine($name));
        if ($name === '') {
            $name = $url;
        }
        $url = $this->sanitizeUrl($url);
        $line = '- [' . $name . '](' . $url . ')';
        $notes = trim($this->singleLine($notes));
        if ($notes !== '') {
            $line .= ': ' . $notes;
        }
        return $line . "\n";
    }

    public function optionalSection(array $links): string
    {
        if ($links === []) {
            return '';
        }
        $out = $this->sectionHeading('Optional');
        foreach ($links as $link) {
            $name = (string)($link['name'] ?? '');
            $url = (string)($link['url'] ?? '');
            $notes = (string)($link['notes'] ?? '');
            if ($url === '') {
                continue;
            }
            $out .= $this->formatLink($name !== '' ? $name : $url, $url, $notes);
        }
        return $out . "\n";
    }

    /**
     * Merchant-supplied extra markdown. H1 is rewritten to H2 so the file keeps a single site title.
     */
    public function manualMarkdown(string $markdown): string
    {
        $markdown = trim(str_replace("\0", '', $markdown));
        if ($markdown === '') {
            return '';
        }
        $markdown = preg_replace('/^# /m', '## ', $markdown) ?? $markdown;
        return rtrim($markdown) . "\n\n";
    }

    /**
     * Build a complete document (used by unit tests and small fixtures).
     *
     * @param array<int, array{heading: string, links: array<int, array{name: string, url: string, notes?: string}>}> $sections
     * @param array<int, array{name: string, url: string, notes?: string}> $optionalLinks
     */
    public function buildDocument(
        string $title,
        ?string $summary,
        array $detailParagraphs,
        array $sections,
        array $optionalLinks = [],
        string $manualMarkdown = ''
    ): string {
        $out = $this->preamble($title, $summary, $detailParagraphs);
        foreach ($sections as $section) {
            $links = $section['links'] ?? [];
            if ($links === []) {
                continue;
            }
            $out .= $this->sectionHeading((string)$section['heading']);
            foreach ($links as $link) {
                $out .= $this->formatLink(
                    (string)($link['name'] ?? ''),
                    (string)($link['url'] ?? ''),
                    (string)($link['notes'] ?? '')
                );
            }
            $out .= "\n";
        }
        $out .= $this->manualMarkdown($manualMarkdown);
        $out .= $this->optionalSection($optionalLinks);
        return rtrim($out) . "\n";
    }

    public function blockquote(string $text): string
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($text)) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = $this->stripHeadings($line);
            $line = ltrim($line, '> ');
            $out[] = '> ' . $line;
        }
        return implode("\n", $out);
    }

    public function stripHeadings(string $text): string
    {
        return preg_replace('/^#{1,6}\s+/m', '', $text) ?? $text;
    }

    public function singleLine(string $text): string
    {
        $text = str_replace(["\r", "\n", "\0"], ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        return trim($text);
    }

    public function escapeLinkText(string $name): string
    {
        return str_replace(['[', ']'], ['\\[', '\\]'], $name);
    }

    public function sanitizeUrl(string $url): string
    {
        $url = $this->singleLine($url);
        $url = str_replace(['(', ')', ' '], ['%28', '%29', '%20'], $url);
        return $url;
    }
}
