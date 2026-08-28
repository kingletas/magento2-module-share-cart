<?php
/**
 * InMemorySharedCartRepository.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Behaviour\Fake;

use Commerce\Foundation\Api\TokenGeneratorInterface;
use Commerce\ShareCart\Api\Data\SharedCartInterface;
use Commerce\ShareCart\Api\Data\SharedCartSearchResultsInterface;
use Commerce\ShareCart\Api\SharedCartRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;

/**
 * The shared-cart table, in an array, with its two rules intact.
 */
class InMemorySharedCartRepository implements SharedCartRepositoryInterface
{
    /** @var array<int, SharedCartInterface> Keyed by id. */
    private array $rows = [];

    private int $nextId = 1;

    /** @var int[] Ids removed by purgeExpired(), in order. */
    public array $purged = [];

    /**
     * @param string $now The clock, as "Y-m-d H:i:s" in UTC.
     */
    public function __construct(
        private readonly TokenGeneratorInterface $tokenGenerator,
        private string $now = '2026-08-27 09:00:00'
    ) {
    }

    public function setNow(string $now): void
    {
        $this->now = $now;
    }

    public function getById(int $sharedCartId): SharedCartInterface
    {
        return $this->rows[$sharedCartId] ?? throw $this->notValid();
    }

    public function getByToken(string $token): SharedCartInterface
    {
        $digest = $this->tokenGenerator->hash($token);

        foreach ($this->rows as $row) {
            if ($row->getTokenHash() === $digest) {
                return $this->hasExpired($row) ? throw $this->notValid() : $row;
            }
        }

        throw $this->notValid();
    }

    public function getByQuoteId(int $quoteId): SharedCartInterface
    {
        foreach ($this->rows as $row) {
            if ($row->getQuoteId() === $quoteId) {
                return $row;
            }
        }

        throw $this->notValid();
    }

    public function getList(SearchCriteriaInterface $searchCriteria): SharedCartSearchResultsInterface
    {
        throw new \BadMethodCallException('The behaviour suite does not list shared carts.');
    }

    public function save(SharedCartInterface $sharedCart): SharedCartInterface
    {
        if ($sharedCart->getSharedCartId() === null) {
            $sharedCart->setSharedCartId($this->nextId++);
        }

        $this->rows[(int) $sharedCart->getSharedCartId()] = $sharedCart;

        return $sharedCart;
    }

    public function delete(SharedCartInterface $sharedCart): void
    {
        unset($this->rows[(int) $sharedCart->getSharedCartId()]);
    }

    public function deleteById(int $sharedCartId): void
    {
        unset($this->rows[$sharedCartId]);
    }

    public function purgeExpired(): int
    {
        $removed = 0;

        foreach ($this->rows as $id => $row) {
            if ($this->hasExpired($row)) {
                $this->purged[] = $id;
                unset($this->rows[$id]);
                $removed++;
            }
        }

        return $removed;
    }

    public function count(): int
    {
        return count($this->rows);
    }

    /**
     * A row with no expiry never expires, which is what a lifetime of zero days
     * is configured to mean.
     */
    private function hasExpired(SharedCartInterface $sharedCart): bool
    {
        $expiresAt = $sharedCart->getExpiresAt();

        return $expiresAt !== null && $expiresAt < $this->now;
    }

    /**
     * One message for both "no such token" and "expired token", deliberately.
     */
    private function notValid(): NoSuchEntityException
    {
        return new NoSuchEntityException(new Phrase('That shared cart link is not valid.'));
    }
}
