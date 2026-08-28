<?php
/**
 * ExpressButtonPoolTest.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Model\Checkout;

use Commerce\ShareCart\Api\Checkout\ExpressButtonInterface;
use Commerce\ShareCart\Model\Checkout\ExpressButtonPool;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ExpressButtonPoolTest extends TestCase
{
    /**
     * A store with no express payment methods must get a working, empty pool.
     */
    public function testAnEmptyPoolIsValid(): void
    {
        $pool = new ExpressButtonPool($this->createMock(LoggerInterface::class));

        self::assertSame([], $pool->getAvailable());
    }

    public function testReturnsAvailableButtonsInSortOrder(): void
    {
        $pool = new ExpressButtonPool($this->createMock(LoggerInterface::class), [
            'late' => $this->button('late', true, 30),
            'early' => $this->button('early', true, 10),
            'middle' => $this->button('middle', true, 20),
        ]);

        $codes = array_map(
            static fn (ExpressButtonInterface $b): string => $b->getCode(),
            $pool->getAvailable()
        );

        self::assertSame(['early', 'middle', 'late'], $codes);
    }

    public function testUnavailableButtonsAreOmitted(): void
    {
        $pool = new ExpressButtonPool($this->createMock(LoggerInterface::class), [
            'on' => $this->button('on', true),
            'off' => $this->button('off', false),
        ]);

        self::assertCount(1, $pool->getAvailable());
    }

    /**
     * One broken payment integration must not take the cart page down.
     */
    public function testAThrowingButtonIsLoggedAndSkipped(): void
    {
        $broken = $this->createMock(ExpressButtonInterface::class);
        $broken->method('getCode')->willReturn('broken');
        $broken->method('getSortOrder')->willReturn(10);
        $broken->method('isAvailable')->willThrowException(new RuntimeException('boom'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $pool = new ExpressButtonPool($logger, ['broken' => $broken, 'ok' => $this->button('ok', true, 20)]);

        self::assertCount(1, $pool->getAvailable());
    }

    public function testRejectsANonButtonAtConstructionRatherThanAtRenderTime(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExpressButtonPool($this->createMock(LoggerInterface::class), ['bad' => new \stdClass()]);
    }

    private function button(string $code, bool $available, int $sortOrder = 10): ExpressButtonInterface
    {
        $button = $this->createMock(ExpressButtonInterface::class);
        $button->method('getCode')->willReturn($code);
        $button->method('isAvailable')->willReturn($available);
        $button->method('getSortOrder')->willReturn($sortOrder);
        $button->method('render')->willReturn('<button>' . $code . '</button>');

        return $button;
    }
}
