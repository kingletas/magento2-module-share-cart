<?php
/**
 * SharedCartJourneyTest.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Behaviour;

use Commerce\Foundation\Model\Security\TokenGenerator;
use Commerce\ShareCart\Api\Data\SharedCartInterface;
use Commerce\ShareCart\Api\Data\SharedCartInterfaceFactory;
use Commerce\ShareCart\Cron\PurgeExpiredSharedCarts;
use Commerce\ShareCart\Model\Cart\RestoreOutcome;
use Commerce\ShareCart\Model\Cart\SharedCartRestorer;
use Commerce\ShareCart\Model\Config;
use Commerce\ShareCart\Model\ShareLinkIssuer;
use Commerce\ShareCart\Test\Behaviour\Fake\InMemorySharedCartRepository;
use Commerce\ShareCart\Test\Unit\Fake\ArrayScopeConfig;
use Commerce\ShareCart\Test\Unit\Fake\InMemorySharedCart;
use Commerce\ShareCart\Test\Unit\Fake\RecordingLogger;
use Commerce\ShareCart\Test\Unit\Fake\SnapshotQuote;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\DataObject;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Alice shares her cart, and Bob opens the link.
 */
final class SharedCartJourneyTest extends TestCase
{
    private const SECTION = 'commerce_sharecart';
    private const NOW = '2026-08-27 09:00:00';

    private TokenGenerator $tokens;
    private InMemorySharedCartRepository $sharedCarts;
    private RecordingLogger $logger;

    /** @var array<int, Quote> Quotes the store holds, keyed by id. */
    private array $quotes = [];

    private int $nextQuoteId = 100;
    private int $currentStoreId = 1;

    /** @var array<string, string> */
    private array $settings = [];

    private ?Quote $sessionQuote = null;
    private ?Quote $replacedSessionQuote = null;

    protected function setUp(): void
    {
        $this->tokens = new TokenGenerator();
        $this->sharedCarts = new InMemorySharedCartRepository($this->tokens, self::NOW);
        $this->logger = new RecordingLogger();
        $this->quotes = [];
        $this->nextQuoteId = 100;
        $this->currentStoreId = 1;
        $this->replacedSessionQuote = null;
        $this->settings = [
            self::SECTION . '/general/enabled' => '1',
            self::SECTION . '/general/lifetime_days' => '7',
            self::SECTION . '/general/cleanup_enabled' => '1',
        ];
    }

    public function testTheLinkAliceSharesRebuildsHerCartForBob(): void
    {
        $link = $this->aliceSharesACartOf(3);

        self::assertTrue($link->isSuccess);
        self::assertNotNull($link->token);
        self::assertStringContainsString((string) $link->token, (string) $link->url);

        $result = $this->bobOpens((string) $link->token, withItemsOfHisOwn: 2);

        self::assertSame(RestoreOutcome::Restored, $result->outcome);
        self::assertNotNull($this->replacedSessionQuote, "Bob's session should hold the merged cart.");
    }

    /**
     * The visitor's own quote is merged into rather than reset, so their basket
     * survives.
     */
    public function testBobsOwnItemsAreKeptRatherThanReplaced(): void
    {
        $link = $this->aliceSharesACartOf(3);

        $this->bobOpens((string) $link->token, withItemsOfHisOwn: 2);

        $merged = $this->replacedSessionQuote;
        self::assertInstanceOf(SnapshotQuote::class, $merged);
        self::assertCount(
            2,
            $merged->merged,
            "Bob's existing quote and Alice's snapshot should both have been merged in."
        );
    }

    /**
     * Merging nothing is skipped, so a link opened in a fresh browser does no
     * quote work.
     */
    public function testAVisitorWithAnEmptyCartIsMergedWithTheSnapshotAlone(): void
    {
        $link = $this->aliceSharesACartOf(3);

        $this->bobOpens((string) $link->token, withItemsOfHisOwn: 0);

        $merged = $this->replacedSessionQuote;
        self::assertInstanceOf(SnapshotQuote::class, $merged);
        self::assertCount(1, $merged->merged);
    }

    /**
     * The plaintext exists only in the URL, so read access to the database is
     * not read access to shoppers' carts.
     */
    public function testWhatIsStoredIsADigestAndNotTheToken(): void
    {
        $link = $this->aliceSharesACartOf(3);
        $row = $this->sharedCarts->getByToken((string) $link->token);

        self::assertNotSame($link->token, $row->getTokenHash());
        self::assertSame($this->tokens->hash((string) $link->token), $row->getTokenHash());
    }

    /**
     * Not a nicety.
     */
    public function testAnUnknownTokenAndAnExpiredTokenAreIndistinguishable(): void
    {
        $link = $this->aliceSharesACartOf(3);

        $unknown = $this->bobOpens($this->tokens->generate(), withItemsOfHisOwn: 0);

        // Eight days later, with a seven-day lifetime.
        $this->sharedCarts->setNow('2026-09-04 09:00:00');
        $expired = $this->bobOpens((string) $link->token, withItemsOfHisOwn: 0);

        self::assertSame(RestoreOutcome::NotFound, $unknown->outcome);
        self::assertSame(RestoreOutcome::NotFound, $expired->outcome);
        self::assertSame((string) $unknown->message, (string) $expired->message);
    }

    /**
     * Prices, tax and availability are per store, so the cart is rebuilt in the
     * right one.
     */
    public function testALinkIssuedOnOneStoreDoesNotOpenOnAnother(): void
    {
        $link = $this->aliceSharesACartOf(3);

        $this->currentStoreId = 2;
        $result = $this->bobOpens((string) $link->token, withItemsOfHisOwn: 0);

        self::assertSame(RestoreOutcome::WrongStore, $result->outcome);
    }

    /**
     * A snapshot carries no customer id, email or guest flag from the sharer.
     */
    public function testTheSnapshotCarriesNoneOfTheSharersIdentity(): void
    {
        $this->aliceSharesACartOf(3);

        $snapshot = $this->quotes[$this->nextQuoteId - 1];

        self::assertNull($snapshot->getCustomerId());
        self::assertNull($snapshot->getCustomerEmail());
        self::assertTrue((bool) $snapshot->getCustomerIsGuest());
        self::assertFalse((bool) $snapshot->getIsActive(), 'A snapshot must never be a live cart.');
    }

    /**
     * A shopper clicking Share on an empty cart is using the page, not breaking
     * it.
     */
    public function testSharingAnEmptyCartIsRefusedWithoutBeingLoggedAsAFault(): void
    {
        $link = $this->aliceSharesACartOf(0);

        self::assertFalse($link->isSuccess);
        self::assertSame('There is nothing in your cart to share.', (string) $link->message);
        self::assertSame([], $this->logger->errors);
    }

    public function testTheNightlyCleanupRemovesExpiredLinksAndLeavesLiveOnes(): void
    {
        $expiring = $this->aliceSharesACartOf(3);

        // A second link issued with the lifetime set to "never".
        $this->settings[self::SECTION . '/general/lifetime_days'] = '0';
        $permanent = $this->aliceSharesACartOf(2);

        self::assertSame(2, $this->sharedCarts->count());

        $this->sharedCarts->setNow('2026-09-04 09:00:00');
        $this->cron()->execute();

        self::assertSame(1, $this->sharedCarts->count());
        self::assertSame(
            RestoreOutcome::NotFound,
            $this->bobOpens((string) $expiring->token, withItemsOfHisOwn: 0)->outcome
        );
        self::assertSame(
            RestoreOutcome::Restored,
            $this->bobOpens((string) $permanent->token, withItemsOfHisOwn: 0)->outcome
        );
    }

    /**
     * A store that wants to keep links for audit turns this off, and "off" has
     * to mean the job does not delete rather than the job deleting fewer.
     */
    public function testWithCleanupSwitchedOffTheCronRemovesNothing(): void
    {
        $this->aliceSharesACartOf(3);
        $this->settings[self::SECTION . '/general/cleanup_enabled'] = '0';

        $this->sharedCarts->setNow('2026-09-04 09:00:00');
        $this->cron()->execute();

        self::assertSame(1, $this->sharedCarts->count());
        self::assertSame([], $this->sharedCarts->purged);
    }

    private function aliceSharesACartOf(int $items): \Commerce\ShareCart\Model\ShareLink
    {
        $this->sessionQuote = $this->quote($items, customerId: 42, email: 'alice@example.test');

        return $this->issuer()->issue();
    }

    private function bobOpens(string $token, int $withItemsOfHisOwn): \Commerce\ShareCart\Model\Cart\RestoreResult
    {
        $this->sessionQuote = $this->quote($withItemsOfHisOwn);
        $this->replacedSessionQuote = null;

        return $this->restorer()->restore($token);
    }

    private function issuer(): ShareLinkIssuer
    {
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params = []): string =>
                'https://example.test/' . $route . '/token/' . ($params['token'] ?? '')
        );

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturnCallback(
            static fn (string $format = 'Y-m-d H:i:s', $input = null): string =>
                $input === null ? self::NOW : date($format, (int) $input)
        );

        return new ShareLinkIssuer(
            $this->checkoutSession(),
            $this->cartFactory(),
            $this->cartRepository(),
            $this->sharedCarts,
            $this->sharedCartFactory(),
            $this->storeManager(),
            $this->tokens,
            $urlBuilder,
            $dateTime,
            new Config(new ArrayScopeConfig($this->settings), self::SECTION),
            $this->logger
        );
    }

    private function restorer(): SharedCartRestorer
    {
        return new SharedCartRestorer(
            $this->checkoutSession(),
            $this->cartFactory(),
            $this->cartRepository(),
            $this->sharedCarts,
            $this->storeManager(),
            $this->logger
        );
    }

    private function cron(): PurgeExpiredSharedCarts
    {
        return new PurgeExpiredSharedCarts(
            $this->sharedCarts,
            new Config(new ArrayScopeConfig($this->settings), self::SECTION),
            $this->logger
        );
    }

    private function checkoutSession(): CheckoutSession
    {
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willReturnCallback(fn (): Quote => $this->sessionQuote ?? $this->quote(0));
        $session->method('replaceQuote')->willReturnCallback(function (Quote $quote) use ($session) {
            $this->replacedSessionQuote = $quote;

            return $session;
        });

        return $session;
    }

    private function cartFactory(): CartInterfaceFactory
    {
        $factory = $this->createMock(CartInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(fn (): Quote => new SnapshotQuote());

        return $factory;
    }

    private function cartRepository(): CartRepositoryInterface
    {
        $repository = $this->createMock(CartRepositoryInterface::class);
        $repository->method('save')->willReturnCallback(function (Quote $quote): void {
            if ($quote->getId() === null) {
                $quote->setId($this->nextQuoteId++);
            }

            $this->quotes[(int) $quote->getId()] = $quote;
        });
        $repository->method('get')->willReturnCallback(
            fn (int $quoteId): Quote => $this->quotes[$quoteId] ?? $this->quote(0)
        );

        return $repository;
    }

    private function sharedCartFactory(): SharedCartInterfaceFactory
    {
        $factory = $this->createMock(SharedCartInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(
            static fn (): SharedCartInterface => new InMemorySharedCart()
        );

        return $factory;
    }

    private function storeManager(): StoreManagerInterface
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturnCallback(
            fn (): DataObject => new DataObject(['id' => $this->currentStoreId])
        );

        return $storeManager;
    }

    private function quote(int $items, ?int $customerId = null, ?string $email = null): Quote
    {
        $quote = new SnapshotQuote();
        $quote->setData('items_count', $items);
        $quote->setStoreId($this->currentStoreId);
        $quote->setCustomerId($customerId);
        $quote->setCustomerEmail($email);

        return $quote;
    }
}
