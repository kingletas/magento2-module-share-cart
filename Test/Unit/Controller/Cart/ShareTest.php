<?php
/**
 * ShareTest.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Controller\Cart;

use Commerce\ShareCart\Controller\Cart\Share;
use Commerce\ShareCart\Model\Cart\RestoreOutcome;
use Commerce\ShareCart\Model\Cart\RestoreResult;
use Commerce\ShareCart\Model\Cart\SharedCartRestorer;
use Commerce\ShareCart\Model\Config;
use Commerce\ShareCart\Model\Validator\TokenFormatValidator;
use Commerce\ShareCart\Test\Unit\Fake\ArrayScopeConfig;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Quote\Api\Data\CartInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ShareTest extends TestCase
{
    private ?string $redirectedTo = null;

    /** @var array<int, array{level: string, message: string}> */
    private array $messages = [];

    private string $token = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6'; // pragma: allowlist secret
    private SharedCartRestorer&MockObject $restorer;

    protected function setUp(): void
    {
        $this->redirectedTo = null;
        $this->messages = [];

        $this->restorer = $this->createMock(SharedCartRestorer::class);
        $this->restorer->method('restore')->willReturn(
            new RestoreResult(RestoreOutcome::Restored, $this->createMock(CartInterface::class))
        );
    }

    public function testItOnlyAnswersGets(): void
    {
        $this->assertInstanceOf(HttpGetActionInterface::class, $this->controller());
    }

    public function testARestoredCartLandsOnTheCartWithASuccessMessage(): void
    {
        $this->controller()->execute();

        $this->assertSame('checkout/cart', $this->redirectedTo);
        $this->assertSame('success', $this->messages[0]['level']);
    }

    /**
     * Every outcome ends on the cart, so nothing about the link or the feature
     * is disclosed.
     */
    public function testEveryOutcomeEndsOnTheCartRatherThanTheLoginPage(): void
    {
        $paths = [];

        $this->controller()->execute();
        $paths[] = $this->redirectedTo;

        $this->restorer = $this->createMock(SharedCartRestorer::class);
        $this->restorer->method('restore')->willReturn(new RestoreResult(RestoreOutcome::NotFound));
        $this->controller()->execute();
        $paths[] = $this->redirectedTo;

        $this->token = 'not-a-token';
        $this->controller()->execute();
        $paths[] = $this->redirectedTo;

        $this->controller(enabled: false)->execute();
        $paths[] = $this->redirectedTo;

        $this->assertSame(['checkout/cart', 'checkout/cart', 'checkout/cart', 'checkout/cart'], $paths);
    }

    /**
     * A malformed token is rejected on shape alone.
     */
    public function testAMalformedTokenIsRejectedWithoutTouchingTheDatabase(): void
    {
        $this->token = 'not-a-token';
        $this->restorer = $this->createMock(SharedCartRestorer::class);
        $this->restorer->expects($this->never())->method('restore');

        $this->controller()->execute();

        $this->assertSame('error', $this->messages[0]['level']);
    }

    public function testAMissingTokenParameterIsTreatedAsMalformed(): void
    {
        $this->token = '';
        $this->restorer = $this->createMock(SharedCartRestorer::class);
        $this->restorer->expects($this->never())->method('restore');

        $this->controller()->execute();

        $this->assertSame('error', $this->messages[0]['level']);
    }

    public function testAFailedRestoreShowsTheOutcomesOwnMessage(): void
    {
        $this->restorer = $this->createMock(SharedCartRestorer::class);
        $this->restorer->method('restore')->willReturn(new RestoreResult(RestoreOutcome::WrongStore));

        $this->controller()->execute();

        $this->assertSame('error', $this->messages[0]['level']);
        $this->assertSame(
            (string) RestoreOutcome::WrongStore->message(),
            $this->messages[0]['message']
        );
    }

    /**
     * "No such token" and "expired token" must read identically, or the
     * endpoint becomes an oracle telling an attacker which tokens once existed.
     */
    public function testAnUnknownAndAnExpiredTokenAreIndistinguishable(): void
    {
        $this->token = 'ffffffffffffffffffffffffffffffff'; // pragma: allowlist secret
        $this->restorer = $this->createMock(SharedCartRestorer::class);
        $this->restorer->method('restore')->willReturn(new RestoreResult(RestoreOutcome::NotFound));
        $this->controller()->execute();
        $unknown = $this->messages[0]['message'];

        $this->messages = [];
        $this->token = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6'; // pragma: allowlist secret
        $this->controller()->execute();

        $this->assertSame($unknown, $this->messages[0]['message']);
    }

    /**
     * A disabled feature redirects silently: a message would confirm that the
     * route is real and merely switched off.
     */
    public function testADisabledFeatureRedirectsWithoutSayingAnything(): void
    {
        $this->controller(enabled: false)->execute();

        $this->assertSame([], $this->messages);
    }

    private function controller(bool $enabled = true): Share
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(fn (): string => $this->token);

        $messageManager = $this->createMock(MessageManagerInterface::class);
        $messageManager->method('addSuccessMessage')->willReturnCallback(
            function ($message) use (&$messageManager) {
                $this->messages[] = ['level' => 'success', 'message' => (string) $message];

                return $messageManager;
            }
        );
        $messageManager->method('addErrorMessage')->willReturnCallback(
            function ($message) use (&$messageManager) {
                $this->messages[] = ['level' => 'error', 'message' => (string) $message];

                return $messageManager;
            }
        );

        $config = new Config(
            new ArrayScopeConfig(['test_sharecart/general/enabled' => $enabled ? '1' : '0']),
            'test_sharecart'
        );

        return new Share(
            $request,
            $this->redirectFactory(),
            $messageManager,
            $this->restorer,
            new TokenFormatValidator(),
            $config
        );
    }

    private function redirectFactory(): RedirectFactory
    {
        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(function (string $path) use (&$redirect): Redirect {
            $this->redirectedTo = $path;

            return $redirect;
        });

        $factory = $this->createMock(RedirectFactory::class);
        $factory->method('create')->willReturn($redirect);

        return $factory;
    }
}
