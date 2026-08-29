<?php
/**
 * @package   Commerce_ShareCart
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Cron;

use Commerce\ShareCart\Api\SharedCartRepositoryInterface;
use Commerce\ShareCart\Cron\PurgeExpiredSharedCarts;
use Commerce\ShareCart\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class PurgeExpiredSharedCartsTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private SharedCartRepositoryInterface&MockObject $repository;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->repository = $this->createMock(SharedCartRepositoryInterface::class);
    }

    public function testTheSweepRunsWhenCleanupIsEnabled(): void
    {
        $this->repository->expects($this->once())->method('purgeExpired')->willReturn(4);

        $this->cron(true)->execute();
    }

    /**
     * A store that has turned cleanup off keeps its links past their expiry.
     */
    public function testNothingIsRemovedWhenCleanupIsDisabled(): void
    {
        $this->repository->expects($this->never())->method('purgeExpired');

        $this->cron(false)->execute();
    }

    /**
     * Cron output is read after the fact, so a sweep that removed something has
     * to leave a trace of how much.
     */
    public function testARemovalIsReportedWithItsCount(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('4'));

        $this->repository->method('purgeExpired')->willReturn(4);

        $this->cron(true)->execute();
    }

    /**
     * The sweep runs on a schedule and usually finds nothing.
     */
    public function testAnEmptySweepSaysNothing(): void
    {
        $this->logger->expects($this->never())->method('info');

        $this->repository->method('purgeExpired')->willReturn(0);

        $this->cron(true)->execute();
    }

    /**
     * Cron swallows exceptions into a job status nobody reads.
     */
    public function testAFailingSweepIsLoggedRatherThanThrownAtCron(): void
    {
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('cleanup failed'));
        $this->logger->expects($this->never())->method('info');

        $this->repository->method('purgeExpired')->willThrowException(new RuntimeException('lock wait timeout'));

        $this->cron(true)->execute();
    }

    private function cron(bool $cleanupEnabled): PurgeExpiredSharedCarts
    {
        $config = new Config(
            $this->scopeConfig(['test_sharecart/general/cleanup_enabled' => $cleanupEnabled ? '1' : '0']),
            'test_sharecart'
        );

        return new PurgeExpiredSharedCarts($this->repository, $config, $this->logger);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function scopeConfig(array $values): ScopeConfigInterface
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): mixed => $values[$path] ?? null
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path): bool => !in_array($values[$path] ?? null, [null, '', '0', 0, false], true)
        );

        return $scopeConfig;
    }
}
