<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model\Text;

/**
 * HTML / whitespace normalizer for catalog copy that becomes markdown notes.
 */
class Normalizer
{
    public function fromHtml(string $html, int $maxLength = 240): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\0", '', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);
        if ($maxLength > 0 && mb_strlen($text) > $maxLength) {
            $text = rtrim(mb_substr($text, 0, $maxLength - 1)) . '…';
        }
        return $text;
    }
}
