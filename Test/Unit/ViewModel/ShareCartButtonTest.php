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
use Commerce\ShareCart\Test\Unit\Fake\ArrayScopeConfig;
use Commerce\ShareCart\Test\Unit\Fake\RecordingLogger;
use Commerce\ShareCart\ViewModel\ShareCartButton;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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
        self::assertInstanceOf(ArgumentInterface::class, $this->viewModel());
    }

    public function testTheEnabledFlagComesFromConfiguration(): void
    {
        self::assertTrue($this->viewModel(enabled: true)->isEnabled());
        self::assertFalse($this->viewModel(enabled: false)->isEnabled());
    }

    public function testTheJsConfigCarriesTheGenerateEndpoint(): void
    {
        $config = (array) (new Json())->unserialize($this->viewModel()->getJsonConfig());

        self::assertSame('https://shop.test/sharecart/cart/generate/', $config['generateUrl']);
    }

    /**
     * The endpoint takes a form key and returns a bearer link to the shopper's
     * basket; posting it over plain HTTP would put both on the wire.
     */
    public function testTheGenerateEndpointIsRequestedOverHttps(): void
    {
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->urlBuilder->expects(self::once())
            ->method('getUrl')
            ->with('sharecart/cart/generate', ['_secure' => true])
            ->willReturn('https://shop.test/sharecart/cart/generate/');

        $this->viewModel()->getJsonConfig();
    }

    public function testTheJsConfigIsJsonRatherThanAnArray(): void
    {
        self::assertJson($this->viewModel()->getJsonConfig());
    }

    public function testTheExpressButtonsComeFromThePool(): void
    {
        $available = $this->createMock(ExpressButtonInterface::class);
        $available->method('isAvailable')->willReturn(true);
        $unavailable = $this->createMock(ExpressButtonInterface::class);
        $unavailable->method('isAvailable')->willReturn(false);

        $pool = new ExpressButtonPool(new RecordingLogger(), ['a' => $available, 'b' => $unavailable]);

        self::assertSame([$available], $this->viewModel(pool: $pool)->getExpressButtons());
    }

    /**
     * A store with no express payment methods gets the share control on its
     * own.
     */
    public function testAStoreWithNoExpressMethodsGetsAnEmptyList(): void
    {
        self::assertSame([], $this->viewModel()->getExpressButtons());
    }

    private function viewModel(bool $enabled = true, ?ExpressButtonPool $pool = null): ShareCartButton
    {
        $config = new Config(
            new ArrayScopeConfig(['test_sharecart/general/enabled' => $enabled ? '1' : '0']),
            'test_sharecart'
        );

        return new ShareCartButton(
            $this->urlBuilder,
            new Json(),
            $pool ?? new ExpressButtonPool(new RecordingLogger()),
            $config
        );
    }
}
