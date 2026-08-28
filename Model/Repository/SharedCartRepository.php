<?php
/**
 * SharedCartRepository.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Model\Repository;

use Commerce\Foundation\Api\TokenGeneratorInterface;
use Commerce\Foundation\Model\Repository\SearchResultBuilder;
use Commerce\ShareCart\Api\Data\SharedCartInterface;
use Commerce\ShareCart\Api\Data\SharedCartInterfaceFactory;
use Commerce\ShareCart\Api\Data\SharedCartSearchResultsInterface;
use Commerce\ShareCart\Api\Data\SharedCartSearchResultsInterfaceFactory;
use Commerce\ShareCart\Api\SharedCartRepositoryInterface;
use Commerce\ShareCart\Model\ResourceModel\SharedCart as SharedCartResource;
use Commerce\ShareCart\Model\ResourceModel\SharedCart\CollectionFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Throwable;

class SharedCartRepository implements SharedCartRepositoryInterface
{
    /**
     * Per-request identity map.
     *
     * @var array<string, SharedCartInterface>
     */
    private array $byTokenHash = [];

    public function __construct(
        private readonly SharedCartResource $resource,
        private readonly SharedCartInterfaceFactory $sharedCartFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SearchResultBuilder $searchResultBuilder,
        private readonly SharedCartSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly TokenGeneratorInterface $tokenGenerator,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getById(int $sharedCartId): SharedCartInterface
    {
        $sharedCart = $this->sharedCartFactory->create();
        $this->resource->load($sharedCart, $sharedCartId);

        if ($sharedCart->getSharedCartId() === null) {
            throw NoSuchEntityException::singleField(SharedCartInterface::SHARED_CART_ID, $sharedCartId);
        }

        return $sharedCart;
    }

    /**
     * @inheritDoc
     */
    public function getByToken(string $token): SharedCartInterface
    {
        $tokenHash = $this->tokenGenerator->hash($token);

        if (isset($this->byTokenHash[$tokenHash])) {
            return $this->byTokenHash[$tokenHash];
        }

        $sharedCart = $this->sharedCartFactory->create();
        $this->resource->load($sharedCart, $tokenHash, SharedCartInterface::TOKEN);

        if ($sharedCart->getSharedCartId() === null) {
            // Deliberately vague, so the message cannot separate an unknown
            // token from an expired one.
            throw new NoSuchEntityException(__('That shared cart link is not valid.'));
        }

        if ($this->hasExpired($sharedCart)) {
            throw new NoSuchEntityException(__('That shared cart link is not valid.'));
        }

        return $this->byTokenHash[$tokenHash] = $sharedCart;
    }

    /**
     * @inheritDoc
     */
    public function getByQuoteId(int $quoteId): SharedCartInterface
    {
        $sharedCart = $this->sharedCartFactory->create();
        $this->resource->load($sharedCart, $quoteId, SharedCartInterface::QUOTE_ID);

        if ($sharedCart->getSharedCartId() === null) {
            throw NoSuchEntityException::singleField(SharedCartInterface::QUOTE_ID, $quoteId);
        }

        return $sharedCart;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SharedCartSearchResultsInterface
    {
        /** @var SharedCartSearchResultsInterface $results */
        $results = $this->searchResultBuilder->build(
            $searchCriteria,
            $this->collectionFactory->create(),
            $this->searchResultsFactory->create()
        );

        return $results;
    }

    /**
     * @inheritDoc
     */
    public function save(SharedCartInterface $sharedCart): SharedCartInterface
    {
        try {
            $this->resource->save($sharedCart);
        } catch (Throwable $e) {
            throw new CouldNotSaveException(__('The shared cart could not be saved.'), $e);
        }

        return $sharedCart;
    }

    /**
     * @inheritDoc
     */
    public function delete(SharedCartInterface $sharedCart): void
    {
        try {
            $this->resource->delete($sharedCart);
        } catch (Throwable $e) {
            throw new CouldNotDeleteException(__('The shared cart could not be deleted.'), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $sharedCartId): void
    {
        // Load first.
        $this->delete($this->getById($sharedCartId));
    }

    /**
     * @inheritDoc
     */
    public function purgeExpired(): int
    {
        return $this->resource->deleteExpired($this->dateTime->gmtDate());
    }

    private function hasExpired(SharedCartInterface $sharedCart): bool
    {
        $expiresAt = $sharedCart->getExpiresAt();

        return $expiresAt !== null && strtotime($expiresAt) < strtotime($this->dateTime->gmtDate());
    }
}
