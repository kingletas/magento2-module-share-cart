<?php
/**
 * RestoreResultTest.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Model\Cart;

use Commerce\ShareCart\Model\Cart\RestoreOutcome;
use Commerce\ShareCart\Model\Cart\RestoreResult;
use InvalidArgumentException;
use Magento\Quote\Api\Data\CartInterface;
use PHPUnit\Framework\TestCase;

class RestoreResultTest extends TestCase
{
    public function testASuccessfulRestoreCarriesTheQuoteAndNoMessage(): void
    {
        $quote = $this->createMock(CartInterface::class);
        $result = new RestoreResult(RestoreOutcome::Restored, $quote);

        $this->assertTrue($result->isSuccess());
        $this->assertSame($quote, $result->quote);
        $this->assertNull($result->message);
    }

    public function testAFailureTakesItsMessageFromTheOutcome(): void
    {
        $result = new RestoreResult(RestoreOutcome::WrongStore);

        $this->assertFalse($result->isSuccess());
        $this->assertNull($result->quote);
        $this->assertSame(
            (string) RestoreOutcome::WrongStore->message(),
            (string) $result->message
        );
    }

    /**
     * The controller reads `quote` whenever the outcome is a success, so a
     * success without one is a null dereference two layers from its cause.
     */
    public function testASuccessWithoutAQuoteIsRefusedAtConstruction(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RestoreResult(RestoreOutcome::Restored);
    }
}
