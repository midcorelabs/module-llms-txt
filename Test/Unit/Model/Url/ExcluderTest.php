<?php
/**
 * Copyright © MidCore Labs. All rights reserved.
 * See LICENSE for license details.
 */
declare(strict_types=1);

namespace MidCore\LlmsTxt\Test\Unit\Model\Url;

use MidCore\LlmsTxt\Model\Url\Excluder;
use PHPUnit\Framework\TestCase;

class ExcluderTest extends TestCase
{
    private Excluder $excluder;

    protected function setUp(): void
    {
        $this->excluder = new Excluder();
    }

    /**
     * @dataProvider blockedUrls
     */
    public function testBlockedStorefrontAndApiPaths(string $url): void
    {
        $this->assertTrue($this->excluder->isBlocked($url));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function blockedUrls(): array
    {
        return [
            'checkout' => ['https://example.com/checkout/cart'],
            'customer' => ['https://example.com/customer/account/login'],
            'wishlist' => ['https://example.com/wishlist/'],
            'rest' => ['https://example.com/rest/V1/products'],
            'graphql' => ['https://example.com/graphql'],
            'admin' => ['https://example.com/admin/dashboard'],
        ];
    }

    public function testCatalogUrlsAreAllowed(): void
    {
        $this->assertFalse($this->excluder->isBlocked('https://example.com/widgets.html'));
        $this->assertFalse($this->excluder->isBlocked('https://example.com/about-us'));
    }

    public function testBlockedCmsIdentifiers(): void
    {
        $this->assertTrue($this->excluder->isBlockedIdentifier('no-route'));
        $this->assertFalse($this->excluder->isBlockedIdentifier('about-us'));
    }
}
