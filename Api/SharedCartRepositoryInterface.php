<?php
/**
 * SharedCartRepositoryInterface.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Api;

use Commerce\ShareCart\Api\Data\SharedCartInterface;
use Commerce\ShareCart\Api\Data\SharedCartSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface SharedCartRepositoryInterface
{
    /**
     * @throws NoSuchEntityException
     */
    public function getById(int $sharedCartId): SharedCartInterface;

    /**
     * Look up by plaintext token.
     *
     * @throws NoSuchEntityException When no such token exists, or it has expired.
     */
    public function getByToken(string $token): SharedCartInterface;

    /**
     * @throws NoSuchEntityException
     */
    public function getByQuoteId(int $quoteId): SharedCartInterface;

    public function getList(SearchCriteriaInterface $searchCriteria): SharedCartSearchResultsInterface;

    /**
     * @throws CouldNotSaveException
     */
    public function save(SharedCartInterface $sharedCart): SharedCartInterface;

    /**
     * @throws CouldNotDeleteException
     */
    public function delete(SharedCartInterface $sharedCart): void;

    /**
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $sharedCartId): void;

    /**
     * Remove links whose expiry has passed.
     *
     * @return int Number of rows removed.
     */
    public function purgeExpired(): int;
}
