<?php
/**
 * ShareCartButtonTest.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\ViewModel;

use Commerce\ShareCart\Api\Checkout\ExpressButtonInterface;
use Commerce\ShareCart\Model\Checkout\ExpressButtonPool;
use Commerce\ShareCart\Model\Config;
use Commerce\ShareCart\ViewModel\ShareCartButton;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ShareCartButtonTest extends TestCase
{
    private UrlInterface&MockObject $urlBuilder;

    protected function setUp(): void
    {
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->urlBuilder->method('getUrl')->willReturn('https://shop.test/sharecart/cart/generate/');
    }

    public function testItIsUsableAsALayoutViewModel(): void
    {
        $this->assertInstanceOf(ArgumentInterface::class, $this->viewModel());
    }

    public function testTheEnabledFlagComesFromConfiguration(): void
    {
        $this->assertTrue($this->viewModel(enabled: true)->isEnabled());
        $this->assertFalse($this->viewModel(enabled: false)->isEnabled());
    }

    public function testTheJsConfigCarriesTheGenerateEndpoint(): void
    {
        $config = (array) (new Json())->unserialize($this->viewModel()->getJsonConfig());

        $this->assertSame('https://shop.test/sharecart/cart/generate/', $config['generateUrl']);
    }

    /**
     * The endpoint takes a form key and returns a bearer link to the shopper's
     * basket; posting it over plain HTTP would put both on the wire.
     */
    public function testTheGenerateEndpointIsRequestedOverHttps(): void
    {
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('sharecart/cart/generate', ['_secure' => true])
            ->willReturn('https://shop.test/sharecart/cart/generate/');

        $this->viewModel()->getJsonConfig();
    }

    public function testTheJsConfigIsJsonRatherThanAnArray(): void
    {
        $this->assertJson($this->viewModel()->getJsonConfig());
    }

    public function testTheExpressButtonsComeFromThePool(): void
    {
        $available = $this->createMock(ExpressButtonInterface::class);
        $available->method('isAvailable')->willReturn(true);
        $unavailable = $this->createMock(ExpressButtonInterface::class);
        $unavailable->method('isAvailable')->willReturn(false);

        $pool = new ExpressButtonPool($this->createMock(LoggerInterface::class), ['a' => $available, 'b' => $unavailable]);

        $this->assertSame([$available], $this->viewModel(pool: $pool)->getExpressButtons());
    }

    /**
     * A store with no express payment methods gets the share control on its
     * own.
     */
    public function testAStoreWithNoExpressMethodsGetsAnEmptyList(): void
    {
        $this->assertSame([], $this->viewModel()->getExpressButtons());
    }

    private function viewModel(bool $enabled = true, ?ExpressButtonPool $pool = null): ShareCartButton
    {
        $config = new Config(
            $this->scopeConfig(['test_sharecart/general/enabled' => $enabled ? '1' : '0']),
            'test_sharecart'
        );

        return new ShareCartButton(
            $this->urlBuilder,
            new Json(),
            $pool ?? new ExpressButtonPool($this->createMock(LoggerInterface::class)),
            $config
        );
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
