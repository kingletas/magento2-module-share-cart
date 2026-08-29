<?php
/**
 * @package   Commerce_ShareCart
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Model;

use Commerce\ShareCart\Model\ShareLink;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ShareLinkTest extends TestCase
{
    public function testASuccessCarriesTheTokenAndUrl(): void
    {
        $link = new ShareLink(isSuccess: true, token: 'abc', url: 'https://example.test/c/abc');

        $this->assertTrue($link->isSuccess);
        $this->assertSame('abc', $link->token);
        $this->assertSame('https://example.test/c/abc', $link->url);
        $this->assertNull($link->message);
    }

    public function testAFailureCarriesAMessage(): void
    {
        $link = new ShareLink(isSuccess: false, message: __('No.'));

        $this->assertFalse($link->isSuccess);
        $this->assertSame('No.', (string) $link->message);
    }

    public function testASuccessWithoutAUrlIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ShareLink(isSuccess: true, token: 'abc');
    }

    /**
     * A failure with no message reaches the shopper as an empty error box.
     */
    public function testAFailureWithoutAMessageIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ShareLink(isSuccess: false);
    }
}
