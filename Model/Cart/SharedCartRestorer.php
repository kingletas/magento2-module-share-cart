<?php
/**
 * @package   Commerce_ShareCart
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\ShareCart\Model\Cart;

use Commerce\ShareCart\Api\Data\SharedCartInterface;
use Commerce\ShareCart\Api\SharedCartRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Loads a shared cart into the visitor's session.
 */
class SharedCartRestorer
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CartInterfaceFactory $cartFactory,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly SharedCartRepositoryInterface $sharedCartRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Merge the cart behind $token into the visitor's session.
     *
     * @return RestoreResult Never throws; the caller renders the outcome.
     */
    public function restore(string $token): RestoreResult
    {
        try {
            $sharedCart = $this->sharedCartRepository->getByToken($token);
        } catch (NoSuchEntityException) {
            return new RestoreResult(RestoreOutcome::NotFound);
        }

        if (!$this->isRedeemableHere($sharedCart)) {
            return new RestoreResult(RestoreOutcome::WrongStore);
        }

        try {
            return new RestoreResult(RestoreOutcome::Restored, $this->merge($sharedCart));
        } catch (Throwable $e) {
            // A genuine failure is logged rather than being reported to the
            // shopper as "no such cart".
            $this->logger->error(
                'Share cart: failed to restore a shared cart into the session.',
                ['exception' => $e, 'shared_cart_id' => $sharedCart->getSharedCartId()]
            );

            return new RestoreResult(RestoreOutcome::Failed);
        }
    }

    private function isRedeemableHere(SharedCartInterface $sharedCart): bool
    {
        try {
            $currentStoreId = (int) $this->storeManager->getStore()->getId();
        } catch (NoSuchEntityException) {
            return false;
        }

        return $sharedCart->getStoreId() === $currentStoreId;
    }

    private function merge(SharedCartInterface $sharedCart): Quote
    {
        $sourceQuote = $this->cartRepository->get($sharedCart->getQuoteId());

        /** @var Quote $targetQuote */
        $targetQuote = $this->cartFactory->create();
        $targetQuote->setStoreId($sharedCart->getStoreId());

        // Whatever the visitor already had stays; the shared items are added on
        // top.
        $existingQuote = $this->checkoutSession->getQuote();

        if ($existingQuote->getItemsCount() > 0) {
            $targetQuote->merge($existingQuote);
        }

        $targetQuote->merge($sourceQuote);
        $targetQuote->setIsActive(true);
        $targetQuote->collectTotals();

        $this->cartRepository->save($targetQuote);
        $this->checkoutSession->replaceQuote($targetQuote);

        return $targetQuote;
    }
}
