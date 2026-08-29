<?php
/**
 * @package   Commerce_ShareCart
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\ShareCart\Model;

use Commerce\Foundation\Model\Config\ModuleConfig;

/**
 * Typed access to this module's settings.
 */
class Config extends ModuleConfig
{
    public const int DEFAULT_LIFETIME_DAYS = 30;

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag('general/enabled', $storeId);
    }

    /**
     * How long a share link stays usable.
     */
    public function getLifetimeDays(?int $storeId = null): int
    {
        $value = $this->getInt('general/lifetime_days', self::DEFAULT_LIFETIME_DAYS, $storeId);

        return max(0, $value);
    }

    public function isCleanupEnabled(?int $storeId = null): bool
    {
        return $this->isSetFlag('general/cleanup_enabled', $storeId);
    }
}
