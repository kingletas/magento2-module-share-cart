<?php
/**
 * ShareLinkIssuer.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Model;

use Commerce\Foundation\Api\TokenGeneratorInterface;
use Commerce\ShareCart\Api\Data\SharedCartInterfaceFactory;
use Commerce\ShareCart\Api\SharedCartRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Snapshots the active cart and issues a share link for it.
 */
class ShareLinkIssuer
{
    /**
     * The bound turns a persistent repository fault into a clear error rather
     * than a loop.
     */
    private const int MAX_TOKEN_ATTEMPTS = 5;

    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CartInterfaceFactory $cartFactory,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly SharedCartRepositoryInterface $sharedCartRepository,
        private readonly SharedCartInterfaceFactory $sharedCartFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly TokenGeneratorInterface $tokenGenerator,
        private readonly UrlInterface $urlBuilder,
        private readonly DateTime $dateTime,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function issue(): ShareLink
    {
        try {
            $sourceQuote = $this->checkoutSession->getQuote();
        } catch (Throwable $e) {
            $this->logger->error('Share cart: could not read the checkout session quote.', ['exception' => $e]);

            return new ShareLink(isSuccess: false, message: __('Your cart is not available right now.'));
        }

        if ($sourceQuote->getItemsCount() < 1) {
            return new ShareLink(isSuccess: false, message: __('There is nothing in your cart to share.'));
        }

        try {
            $token = $this->allocateToken();
            $snapshotId = $this->snapshotQuote($sourceQuote);
            $this->persist($snapshotId, $token);
        } catch (Throwable $e) {
            // The shopper gets a stable, translatable sentence; the detail goes
            // to the log where it belongs.
            $this->logger->error('Share cart: could not issue a share link.', ['exception' => $e]);

            return new ShareLink(
                isSuccess: false,
                message: __('We could not create a share link for your cart. Please try again.')
            );
        }

        return new ShareLink(isSuccess: true, token: $token, url: $this->buildUrl($token));
    }

    public function buildUrl(string $token): string
    {
        return $this->urlBuilder->getUrl('sharecart/cart/share', ['token' => $token, '_secure' => true]);
    }

    /**
     * Produce a token that is not already in use.
     *
     * @throws LocalizedException When no free token could be found.
     */
    private function allocateToken(): string
    {
        for ($attempt = 0; $attempt < self::MAX_TOKEN_ATTEMPTS; $attempt++) {
            $token = $this->tokenGenerator->generate();

            try {
                $this->sharedCartRepository->getByToken($token);
            } catch (NoSuchEntityException) {
                // Not found is the success case: the token is free.
                return $token;
            }
        }

        throw new LocalizedException(
            __('Could not allocate a unique share token after %1 attempts.', self::MAX_TOKEN_ATTEMPTS)
        );
    }

    /**
     * Copy the live cart into a detached, inactive quote.
     *
     * @throws LocalizedException
     */
    private function snapshotQuote(Quote $sourceQuote): int
    {
        /** @var Quote $snapshot */
        $snapshot = $this->cartFactory->create();
        $snapshot->setStoreId($sourceQuote->getStoreId());
        $snapshot->merge($sourceQuote);
        $snapshot->setIsActive(false);
        // A snapshot carries no customer id or email, so whoever opens the link
        // inherits neither.
        $snapshot->setCustomerId(null);
        $snapshot->setCustomerIsGuest(true);
        $snapshot->setCustomerEmail(null);

        $this->cartRepository->save($snapshot);

        return (int) $snapshot->getId();
    }

    /**
     * @throws AlreadyExistsException
     */
    private function persist(int $quoteId, string $token): void
    {
        $storeId = (int) $this->storeManager->getStore()->getId();

        $sharedCart = $this->sharedCartFactory->create();
        $sharedCart->setStoreId($storeId);
        $sharedCart->setQuoteId($quoteId);
        $sharedCart->setTokenHash($this->tokenGenerator->hash($token));
        $sharedCart->setExpiresAt($this->calculateExpiry($storeId));

        $this->sharedCartRepository->save($sharedCart);
    }

    private function calculateExpiry(int $storeId): ?string
    {
        $days = $this->config->getLifetimeDays($storeId);

        if ($days === 0) {
            return null;
        }

        return $this->dateTime->gmtDate(
            'Y-m-d H:i:s',
            strtotime(sprintf('+%d days', $days), $this->dateTime->gmtTimestamp())
        );
    }
}
