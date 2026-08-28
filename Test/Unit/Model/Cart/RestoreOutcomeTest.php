<?php
/**
 * RestoreOutcomeTest.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Model\Cart;

use Commerce\ShareCart\Model\Cart\RestoreOutcome;
use PHPUnit\Framework\TestCase;

class RestoreOutcomeTest extends TestCase
{
    public function testOnlyARestoredOutcomeCountsAsSuccess(): void
    {
        $this->assertTrue(RestoreOutcome::Restored->isSuccess());
        $this->assertFalse(RestoreOutcome::NotFound->isSuccess());
        $this->assertFalse(RestoreOutcome::WrongStore->isSuccess());
        $this->assertFalse(RestoreOutcome::Failed->isSuccess());
    }

    /**
     * The three failure states exist to be told apart.
     */
    public function testEveryFailureStateHasItsOwnMessage(): void
    {
        $messages = [
            (string) RestoreOutcome::NotFound->message(),
            (string) RestoreOutcome::WrongStore->message(),
            (string) RestoreOutcome::Failed->message(),
        ];

        $this->assertSame($messages, array_unique($messages));
        $this->assertNotContains('', $messages);
    }

    public function testASuccessfulRestoreHasNothingToApologiseFor(): void
    {
        $this->assertNull(RestoreOutcome::Restored->message());
    }
}
