<?php
/**
 * GenerateTest.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Controller\Cart;

use Commerce\ShareCart\Controller\Cart\Generate;
use Commerce\ShareCart\Model\Config;
use Commerce\ShareCart\Model\ShareLink;
use Commerce\ShareCart\Model\ShareLinkIssuer;
use Commerce\ShareCart\Test\Unit\Fake\ArrayScopeConfig;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GenerateTest extends TestCase
{
    private ?int $status = null;

    /** @var array<string, mixed> */
    private array $data = [];

    private FormKeyValidator&MockObject $formKeyValidator;
    private ShareLinkIssuer&MockObject $shareLinkIssuer;

    protected function setUp(): void
    {
        $this->status = null;
        $this->data = [];

        $this->formKeyValidator = $this->createMock(FormKeyValidator::class);
        $this->formKeyValidator->method('validate')->willReturn(true);

        $this->shareLinkIssuer = $this->createMock(ShareLinkIssuer::class);
        $this->shareLinkIssuer->method('issue')
            ->willReturn(new ShareLink(true, 'tok3n', 'https://shop.test/sharecart/cart/share/token/tok3n'));
    }

    public function testItOnlyAnswersPosts(): void
    {
        $this->assertInstanceOf(HttpPostActionInterface::class, $this->controller());
    }

    public function testASuccessfulShareReturnsTheTokenAndItsUrl(): void
    {
        $this->controller()->execute();

        $this->assertFalse($this->data['error']);
        $this->assertSame('tok3n', $this->data['token']);
        $this->assertSame('https://shop.test/sharecart/cart/share/token/tok3n', $this->data['url']);
        $this->assertNull($this->status);
    }

    /**
     * The action mutates state and is reachable by any visitor, so a stale form
     * key is rejected.
     */
    public function testARejectedFormKeyIsForbiddenAndIssuesNothing(): void
    {
        $this->formKeyValidator = $this->createMock(FormKeyValidator::class);
        $this->formKeyValidator->method('validate')->willReturn(false);
        $this->shareLinkIssuer = $this->createMock(ShareLinkIssuer::class);
        $this->shareLinkIssuer->expects($this->never())->method('issue');

        $this->controller()->execute();

        $this->assertSame(403, $this->status);
        $this->assertTrue($this->data['error']);
    }

    /**
     * A disabled feature must not be distinguishable from a route that was
     * never registered - a 403 here would confirm the endpoint exists.
     */
    public function testADisabledFeatureIsNotFoundRatherThanForbidden(): void
    {
        $this->controller(enabled: false)->execute();

        $this->assertSame(404, $this->status);
        $this->assertTrue($this->data['error']);
    }

    /**
     * The config check comes before the form key, so a disabled feature
     * validates nothing.
     */
    public function testADisabledFeatureIsRefusedWithoutValidatingAnything(): void
    {
        $this->formKeyValidator = $this->createMock(FormKeyValidator::class);
        $this->formKeyValidator->expects($this->never())->method('validate');

        $this->controller(enabled: false)->execute();
    }

    /**
     * An empty basket is neither a server error nor a success, and says which
     * it is.
     */
    public function testARefusedIssueIsReportedAsUnprocessable(): void
    {
        $this->shareLinkIssuer = $this->createMock(ShareLinkIssuer::class);
        $this->shareLinkIssuer->method('issue')
            ->willReturn(new ShareLink(false, message: __('Your cart is empty.')));

        $this->controller()->execute();

        $this->assertSame(422, $this->status);
        $this->assertTrue($this->data['error']);
        $this->assertSame('Your cart is empty.', (string) $this->data['message']);
    }

    /**
     * Every outcome answering 200 forces the client to parse the body to find
     * out whether it worked, which is what the previous version did.
     */
    public function testTheThreeFailureModesAreDistinguishableByStatusAlone(): void
    {
        $statuses = [];

        $this->controller(enabled: false)->execute();
        $statuses[] = $this->status;

        $this->formKeyValidator = $this->createMock(FormKeyValidator::class);
        $this->formKeyValidator->method('validate')->willReturn(false);
        $this->controller()->execute();
        $statuses[] = $this->status;

        $this->formKeyValidator = $this->createMock(FormKeyValidator::class);
        $this->formKeyValidator->method('validate')->willReturn(true);
        $this->shareLinkIssuer = $this->createMock(ShareLinkIssuer::class);
        $this->shareLinkIssuer->method('issue')->willReturn(new ShareLink(false, message: __('No.')));
        $this->controller()->execute();
        $statuses[] = $this->status;

        $this->assertSame([404, 403, 422], $statuses);
    }

    private function controller(bool $enabled = true): Generate
    {
        $config = new Config(
            new ArrayScopeConfig(['test_sharecart/general/enabled' => $enabled ? '1' : '0']),
            'test_sharecart'
        );

        return new Generate(
            $this->createMock(RequestInterface::class),
            $this->jsonFactory(),
            $this->formKeyValidator,
            $this->shareLinkIssuer,
            $config
        );
    }

    private function jsonFactory(): JsonFactory
    {
        $json = $this->createMock(Json::class);
        $json->method('setHttpResponseCode')->willReturnCallback(function (int $code) use (&$json): Json {
            $this->status = $code;

            return $json;
        });
        $json->method('setData')->willReturnCallback(function ($data) use (&$json): Json {
            $this->data = (array) $data;

            return $json;
        });

        $factory = $this->createMock(JsonFactory::class);
        $factory->method('create')->willReturn($json);

        return $factory;
    }
}
