<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Model;

use Commerce\Foundation\Api\TokenGeneratorInterface;
use Commerce\ShareCart\Api\Data\SharedCartInterface;
use Commerce\ShareCart\Api\Data\SharedCartInterfaceFactory;
use Commerce\ShareCart\Api\SharedCartRepositoryInterface;
use Commerce\ShareCart\Model\Config;
use Commerce\ShareCart\Model\ShareLinkIssuer;
use Commerce\ShareCart\Test\Unit\Fake\InMemorySharedCart;
use Commerce\ShareCart\Test\Unit\Fake\RecordingLogger;
use Commerce\ShareCart\Test\Unit\Fake\SnapshotQuote;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Three behaviours, each of them a production incident in waiting if it goes
 * the other way.
 */
final class ShareLinkIssuerTest extends TestCase
{
    private RecordingLogger $logger;
    private int $tokenCalls = 0;

    /** @var Quote[] */
    private array $savedQuotes = [];

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
        $this->tokenCalls = 0;
        $this->savedQuotes = [];
    }

    public function testAnEmptyCartIsRefusedWithAFixedSentence(): void
    {
        $link = $this->issuer(itemsCount: 0)->issue();

        self::assertFalse($link->isSuccess);
        self::assertSame('There is nothing in your cart to share.', (string) $link->message);
        self::assertNull($link->token);
    }

    public function testAnEmptyCartIsNotLoggedAsAnError(): void
    {
        $this->issuer(itemsCount: 0)->issue();

        self::assertSame([], $this->logger->errors, 'An empty cart is a shopper action, not a fault.');
    }

    public function testASuccessfulIssueReturnsATokenAndAUrl(): void
    {
        $link = $this->issuer()->issue();

        self::assertTrue($link->isSuccess);
        self::assertSame('token-1', $link->token);
        self::assertSame('https://example.test/sharecart/cart/share/token/token-1', $link->url);
    }

    /**
     * The unbounded recursion.
     */
    public function testAnAlwaysCollidingTokenGivesUpRatherThanRecursing(): void
    {
        $link = $this->issuer(tokenAlwaysTaken: true)->issue();

        self::assertFalse($link->isSuccess);
        self::assertLessThanOrEqual(
            5,
            $this->tokenCalls,
            'Allocation must be bounded, not recursive.'
        );
    }

    public function testAnAlwaysCollidingTokenShowsTheGenericMessageNotTheInternalOne(): void
    {
        $link = $this->issuer(tokenAlwaysTaken: true)->issue();

        self::assertSame(
            'We could not create a share link for your cart. Please try again.',
            (string) $link->message
        );
        self::assertStringNotContainsString('token', (string) $link->message);
    }

    public function testTheInternalReasonReachesTheLogEvenThoughTheShopperNeverSeesIt(): void
    {
        $this->issuer(tokenAlwaysTaken: true)->issue();

        self::assertCount(1, $this->logger->errors);
        self::assertArrayHasKey('exception', $this->logger->errors[0]['context']);
    }

    /**
     * A broken checkout session must not become a stack trace on the storefront.
     */
    public function testAnUnreadableCheckoutSessionIsContained(): void
    {
        $link = $this->issuer(sessionThrows: true)->issue();

        self::assertFalse($link->isSuccess);
        self::assertSame('Your cart is not available right now.', (string) $link->message);
        self::assertCount(1, $this->logger->errors);
    }

    /**
     * Every failure produces the same fixed sentence, because the difference is
     * what leaks.
     */
    public function testASaveFailureShowsTheSameFixedSentence(): void
    {
        $link = $this->issuer(saveThrows: true)->issue();

        self::assertFalse($link->isSuccess);
        self::assertSame(
            'We could not create a share link for your cart. Please try again.',
            (string) $link->message
        );
        self::assertStringNotContainsString('SQLSTATE', (string) $link->message);
    }

    /**
     * The snapshot carries no customer id or email from the sharer.
     */
    public function testTheSnapshotDropsTheSharersIdentity(): void
    {
        $this->issuer()->issue();

        self::assertCount(1, $this->savedQuotes);
        $snapshot = $this->savedQuotes[0];

        self::assertNull($snapshot->getCustomerId());
        self::assertNull($snapshot->getCustomerEmail());
        self::assertTrue((bool) $snapshot->getCustomerIsGuest());
    }

    public function testTheSnapshotIsSavedInactiveSoItIsNeverPickedUpAsALiveCart(): void
    {
        $this->issuer()->issue();

        self::assertFalse((bool) $this->savedQuotes[0]->getIsActive());
    }

    public function testAZeroLifetimeMeansTheLinkNeverExpires(): void
    {
        $shared = $this->sharedCart();

        $this->issuer(lifetimeDays: 0, sharedCart: $shared)->issue();

        self::assertNull($shared->getExpiresAt());
    }

    public function testANonZeroLifetimeSetsAnExpiry(): void
    {
        $shared = $this->sharedCart();

        $this->issuer(lifetimeDays: 7, sharedCart: $shared)->issue();

        self::assertNotNull($shared->getExpiresAt());
    }

    /**
     * The stored value is a hash, never the token itself.
     */
    public function testOnlyAHashOfTheTokenIsStored(): void
    {
        $shared = $this->sharedCart();

        $link = $this->issuer(sharedCart: $shared)->issue();

        self::assertSame('hash-of-' . $link->token, $shared->getTokenHash());
        self::assertNotSame($link->token, $shared->getTokenHash());
    }

    private function issuer(
        int $itemsCount = 2,
        bool $tokenAlwaysTaken = false,
        bool $sessionThrows = false,
        bool $saveThrows = false,
        int $lifetimeDays = 30,
        ?SharedCartInterface $sharedCart = null
    ): ShareLinkIssuer {
        $sourceQuote = $this->createMock(Quote::class);
        $sourceQuote->method('getItemsCount')->willReturn($itemsCount);
        $sourceQuote->method('getStoreId')->willReturn(1);

        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willReturnCallback(
            function () use ($sessionThrows, $sourceQuote): Quote {
                if ($sessionThrows) {
                    throw new RuntimeException('session store is down');
                }

                return $sourceQuote;
            }
        );

        $snapshot = new SnapshotQuote();
        $cartFactory = $this->createMock(CartInterfaceFactory::class);
        $cartFactory->method('create')->willReturn($snapshot);

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('save')->willReturnCallback(
            function (Quote $quote) use ($saveThrows): void {
                if ($saveThrows) {
                    throw new RuntimeException('SQLSTATE[HY000]: the quote table is locked');
                }

                $quote->setId(99);
                $this->savedQuotes[] = $quote;
            }
        );

        $repository = $this->createMock(SharedCartRepositoryInterface::class);
        $repository->method('getByToken')->willReturnCallback(
            function (string $token) use ($tokenAlwaysTaken): SharedCartInterface {
                if ($tokenAlwaysTaken) {
                    return $this->createMock(SharedCartInterface::class);
                }

                throw new NoSuchEntityException(__('No such shared cart.'));
            }
        );

        $sharedCart ??= $this->sharedCart();
        $sharedFactory = $this->createMock(SharedCartInterfaceFactory::class);
        $sharedFactory->method('create')->willReturn($sharedCart);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $tokens = $this->createMock(TokenGeneratorInterface::class);
        $tokens->method('generate')->willReturnCallback(function (): string {
            $this->tokenCalls++;

            return 'token-' . $this->tokenCalls;
        });
        $tokens->method('hash')->willReturnCallback(static fn (string $t): string => 'hash-of-' . $t);

        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params): string =>
                'https://example.test/' . $route . '/token/' . $params['token']
        );

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturn('2026-09-25 00:00:00');

        $config = $this->createMock(Config::class);
        $config->method('getLifetimeDays')->willReturn($lifetimeDays);

        return new ShareLinkIssuer(
            $session,
            $cartFactory,
            $cartRepository,
            $repository,
            $sharedFactory,
            $storeManager,
            $tokens,
            $url,
            $dateTime,
            $config,
            $this->logger
        );
    }

    private function sharedCart(): SharedCartInterface
    {
        return new InMemorySharedCart();
    }
}
