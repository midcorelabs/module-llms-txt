<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Model\Url;

/**
 * Checkout / account / API / admin URLs are excluded by design.
 */
class Excluder
{
    /**
     * Path needles matched case-insensitively against the URL path.
     *
     * @var string[]
     */
    private const BLOCKED_NEEDLES = [
        '/checkout',
        '/cart',
        '/customer',
        '/wishlist',
        '/rest/',
        '/graphql',
        '/admin',
        '/v1/',
        '/soap/',
    ];

    /**
     * CMS identifiers that are never useful to agents.
     *
     * @var string[]
     */
    private const BLOCKED_IDENTIFIERS = [
        'no-route',
        'enable-cookies',
        'service-unavailable',
        'privacy-policy-cookie-restriction-mode',
    ];

    public function isBlocked(string $url): bool
    {
        $path = strtolower((string)(parse_url($url, PHP_URL_PATH) ?? $url));
        if ($path === '') {
            $path = strtolower($url);
        }
        foreach (self::BLOCKED_NEEDLES as $needle) {
            if (str_contains($path, $needle)) {
                return true;
            }
        }
        return false;
    }

    public function isBlockedIdentifier(string $identifier): bool
    {
        return in_array(strtolower(trim($identifier)), self::BLOCKED_IDENTIFIERS, true);
    }
}
