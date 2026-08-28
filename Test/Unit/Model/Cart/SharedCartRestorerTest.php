<?php
/**
 * SharedCartRestorerTest.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Model\Cart;

use Commerce\ShareCart\Api\Data\SharedCartInterface;
use Commerce\ShareCart\Api\SharedCartRepositoryInterface;
use Commerce\ShareCart\Model\Cart\RestoreOutcome;
use Commerce\ShareCart\Model\Cart\SharedCartRestorer;
use Commerce\ShareCart\Test\Unit\Fake\InMemorySharedCart;
use Commerce\ShareCart\Test\Unit\Fake\SnapshotQuote;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SharedCartRestorerTest extends TestCase
{
    private const TOKEN = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6'; // pragma: allowlist secret

    private LoggerInterface&MockObject $logger;
    private SnapshotQuote $targetQuote;
    private SnapshotQuote $sessionQuote;
    private SnapshotQuote $sourceQuote;
    private CheckoutSession&MockObject $checkoutSession;
    private CartRepositoryInterface&MockObject $cartRepository;
    private SharedCartRepositoryInterface&MockObject $sharedCartRepository;

    private int $currentStoreId = 1;

    /** @var Quote[] */
    private array $savedQuotes = [];

    /** @var Quote[] */
    private array $replacedQuotes = [];

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->savedQuotes = [];
        $this->replacedQuotes = [];

        $this->targetQuote = new SnapshotQuote();
        $this->sourceQuote = new SnapshotQuote();
        $this->sessionQuote = new SnapshotQuote();
        $this->sessionQuote->setData('items_count', 0);

        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->checkoutSession->method('getQuote')->willReturnCallback(fn (): Quote => $this->sessionQuote);
        $this->checkoutSession->method('replaceQuote')->willReturnCallback(
            function (Quote $quote): CheckoutSession {
                $this->replacedQuotes[] = $quote;

                return $this->checkoutSession;
            }
        );

        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->cartRepository->method('get')->willReturnCallback(fn (): Quote => $this->sourceQuote);
        $this->cartRepository->method('save')->willReturnCallback(
            function (\Magento\Quote\Api\Data\CartInterface $quote): void {
                $this->savedQuotes[] = $quote;
            }
        );

        $this->sharedCartRepository = $this->createMock(SharedCartRepositoryInterface::class);
        $this->sharedCartRepository->method('getByToken')->willReturn($this->sharedCart(storeId: 1, quoteId: 77));
    }

    public function testAValidTokenRestoresTheSharedCartIntoTheSession(): void
    {
        $result = $this->restorer()->restore(self::TOKEN);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(RestoreOutcome::Restored, $result->outcome);
        $this->assertSame($this->targetQuote, $result->quote);
        $this->assertSame([$this->targetQuote], $this->replacedQuotes);
    }

    public function testTheRestoredQuoteIsSavedActiveWithItsTotalsCollected(): void
    {
        $this->restorer()->restore(self::TOKEN);

        $this->assertSame([$this->targetQuote], $this->savedQuotes);
        $this->assertTrue((bool) $this->targetQuote->getData('is_active'));
        $this->assertTrue($this->targetQuote->totalsCollected);
    }

    public function testTheSharedItemsAreMergedIntoTheNewQuote(): void
    {
        $this->restorer()->restore(self::TOKEN);

        $this->assertSame([$this->sourceQuote], $this->targetQuote->merged);
    }

    /**
     * The visitor's own basket is merged in first.
     */
    public function testAnExistingBasketIsKeptAndTheSharedItemsAddedOnTop(): void
    {
        $this->sessionQuote->setData('items_count', 3);

        $this->restorer()->restore(self::TOKEN);

        $this->assertSame([$this->sessionQuote, $this->sourceQuote], $this->targetQuote->merged);
    }

    /**
     * An empty session quote has nothing to contribute, and merging it costs a
     * full item walk against the product repository.
     */
    public function testAnEmptyBasketIsNotMerged(): void
    {
        $this->restorer()->restore(self::TOKEN);

        $this->assertSame([$this->sourceQuote], $this->targetQuote->merged);
    }

    public function testTheNewQuoteTakesTheStoreTheLinkWasIssuedOn(): void
    {
        $this->restorer()->restore(self::TOKEN);

        $this->assertSame(1, (int) $this->targetQuote->getData('store_id'));
    }

    /**
     * A link is only redeemed on the store it was issued on, or the prices
     * would be wrong.
     */
    public function testALinkFromAnotherStoreIsRefusedRatherThanRedeemed(): void
    {
        $this->currentStoreId = 2;

        $result = $this->restorer()->restore(self::TOKEN);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(RestoreOutcome::WrongStore, $result->outcome);
        $this->assertSame([], $this->savedQuotes);
        $this->assertSame([], $this->replacedQuotes);
    }

    public function testAnUnknownTokenIsReportedAsNotFound(): void
    {
        $this->logger->expects($this->never())->method('error');

        $this->sharedCartRepository = $this->createMock(SharedCartRepositoryInterface::class);
        $this->sharedCartRepository->method('getByToken')
            ->willThrowException(new NoSuchEntityException(__('That shared cart link is not valid.')));

        $result = $this->restorer()->restore(self::TOKEN);

        $this->assertSame(RestoreOutcome::NotFound, $result->outcome);
    }

    /**
     * A database failure is reported as a failure rather than as "cart not
     * found".
     */
    public function testARealFailureIsReportedAsAFailureAndLogged(): void
    {
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->anything(),
                $this->callback(
                    static fn (array $context): bool => $context['exception'] instanceof RuntimeException
                )
            );

        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->cartRepository->method('get')->willThrowException(new RuntimeException('Deadlock on quote'));

        $result = $this->restorer()->restore(self::TOKEN);

        $this->assertSame(RestoreOutcome::Failed, $result->outcome);
        $this->assertFalse($result->isSuccess());
    }

    /**
     * Detail goes to the log; the shopper gets the outcome's own wording, which
     * invites a retry rather than blaming their link.
     */
    public function testTheShopperIsNotToldWhatBrokeInternally(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->cartRepository->method('get')->willThrowException(new RuntimeException('Deadlock on quote'));

        $result = $this->restorer()->restore(self::TOKEN);

        $this->assertStringNotContainsString('Deadlock', (string) $result->message);
    }

    /**
     * A store that no longer exists cannot be the one the link belongs to.
     */
    public function testAnUnresolvableCurrentStoreRefusesTheLink(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')
            ->willThrowException(new NoSuchEntityException(__('No such store.')));

        $result = $this->restorer($storeManager)->restore(self::TOKEN);

        $this->assertSame(RestoreOutcome::WrongStore, $result->outcome);
    }

    public function testTheTokenIsLookedUpExactlyOnce(): void
    {
        $this->sharedCartRepository = $this->createMock(SharedCartRepositoryInterface::class);
        $this->sharedCartRepository->expects($this->once())
            ->method('getByToken')
            ->with(self::TOKEN)
            ->willReturn($this->sharedCart(1, 77));

        $this->restorer()->restore(self::TOKEN);
    }

    private function sharedCart(int $storeId, int $quoteId): SharedCartInterface
    {
        return (new InMemorySharedCart())
            ->setSharedCartId(5)
            ->setStoreId($storeId)
            ->setQuoteId($quoteId);
    }

    private function restorer(?StoreManagerInterface $storeManager = null): SharedCartRestorer
    {
        $cartFactory = $this->createMock(CartInterfaceFactory::class);
        $cartFactory->method('create')->willReturnCallback(fn (): Quote => $this->targetQuote);

        if ($storeManager === null) {
            $store = $this->createMock(StoreInterface::class);
            $store->method('getId')->willReturnCallback(fn (): int => $this->currentStoreId);
            $storeManager = $this->createMock(StoreManagerInterface::class);
            $storeManager->method('getStore')->willReturn($store);
        }

        return new SharedCartRestorer(
            $this->checkoutSession,
            $cartFactory,
            $this->cartRepository,
            $this->sharedCartRepository,
            $storeManager,
            $this->logger
        );
    }
}
