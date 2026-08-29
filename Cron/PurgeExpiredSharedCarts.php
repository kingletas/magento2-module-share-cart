<?php
/**
 * @package   Commerce_ShareCart
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\ShareCart\Cron;

use Commerce\ShareCart\Api\SharedCartRepositoryInterface;
use Commerce\ShareCart\Model\Config;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Removes share links whose expiry has passed.
 */
class PurgeExpiredSharedCarts
{
    public function __construct(
        private readonly SharedCartRepositoryInterface $sharedCartRepository,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isCleanupEnabled()) {
            return;
        }

        try {
            $removed = $this->sharedCartRepository->purgeExpired();
        } catch (Throwable $e) {
            $this->logger->error('Share cart: expired-link cleanup failed.', ['exception' => $e]);

            return;
        }

        if ($removed > 0) {
            $this->logger->info(sprintf('Share cart: removed %d expired share link(s).', $removed));
        }
    }
}
