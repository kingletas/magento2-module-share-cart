<?php
/**
 * ConfigTest.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Model;

use Commerce\ShareCart\Model\Config;
use Commerce\ShareCart\Test\Unit\Fake\ArrayScopeConfig;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /**
     * The section id is a di.xml argument, which is what makes `bin/rebrand` a
     * rewrite.
     */
    public function testEveryPathIsReadUnderTheConfiguredSection(): void
    {
        $scopeConfig = new ArrayScopeConfig([
            'acme_sharecart/general/enabled' => '1',
            'acme_sharecart/general/lifetime_days' => '7',
            'acme_sharecart/general/cleanup_enabled' => '1',
        ]);
        $config = new Config($scopeConfig, 'acme_sharecart');

        self::assertTrue($config->isEnabled());
        self::assertSame(7, $config->getLifetimeDays());
        self::assertTrue($config->isCleanupEnabled());
    }

    /**
     * A store admin switching the feature off writes "0", not an empty value.
     */
    public function testTheDisabledFlagIsReadAsAFlagRatherThanForTruthiness(): void
    {
        $config = $this->config(['general/enabled' => '0', 'general/cleanup_enabled' => '0']);

        self::assertFalse($config->isEnabled());
        self::assertFalse($config->isCleanupEnabled());
    }

    public function testAnUnsetFeatureIsOff(): void
    {
        $config = $this->config([]);

        self::assertFalse($config->isEnabled());
        self::assertFalse($config->isCleanupEnabled());
    }

    /**
     * A share link is a bearer credential over someone's basket, so an
     * unconfigured store gets a bounded lifetime rather than an unbounded one.
     */
    public function testTheLifetimeFallsBackToADefaultRatherThanZero(): void
    {
        self::assertSame(Config::DEFAULT_LIFETIME_DAYS, $this->config([])->getLifetimeDays());
        self::assertGreaterThan(0, Config::DEFAULT_LIFETIME_DAYS);
    }

    public function testANonNumericLifetimeFallsBackToTheDefault(): void
    {
        self::assertSame(
            Config::DEFAULT_LIFETIME_DAYS,
            $this->config(['general/lifetime_days' => 'thirty'])->getLifetimeDays()
        );
    }

    /**
     * Zero is a documented setting - links that never expire - so it survives
     * the coercion.
     */
    public function testZeroDisablesExpiryRatherThanRestoringTheDefault(): void
    {
        self::assertSame(0, $this->config(['general/lifetime_days' => '0'])->getLifetimeDays());
    }

    /**
     * A negative lifetime would put every link's expiry in the past, so every
     * link ever issued would be dead on arrival.
     */
    public function testANegativeLifetimeIsClampedToZeroRatherThanExpiringEveryLink(): void
    {
        self::assertSame(0, $this->config(['general/lifetime_days' => '-5'])->getLifetimeDays());
    }

    public function testTheStoreIdIsPassedThroughToTheScopeLookup(): void
    {
        $scopeConfig = new ArrayScopeConfig(['test_sharecart/general/lifetime_days' => '3']);

        self::assertSame(3, (new Config($scopeConfig, 'test_sharecart'))->getLifetimeDays(2));
    }

    /**
     * @param array<string, mixed> $values Paths below the section.
     */
    private function config(array $values): Config
    {
        $qualified = [];

        foreach ($values as $path => $value) {
            $qualified['test_sharecart/' . $path] = $value;
        }

        return new Config(new ArrayScopeConfig($qualified), 'test_sharecart');
    }
}
