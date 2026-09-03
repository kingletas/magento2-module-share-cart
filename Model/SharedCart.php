<?php
/**
 * @package   Commerce_ShareCart
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\ShareCart\Model;

use Commerce\ShareCart\Api\Data\SharedCartInterface;
use Commerce\ShareCart\Model\ResourceModel\SharedCart as SharedCartResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Shared cart entity.
 *
 * @SuppressWarnings("PHPMD.CamelCaseMethodName")
 */
class SharedCart extends AbstractModel implements SharedCartInterface
{
    protected function _construct(): void
    {
        $this->_init(SharedCartResource::class);
    }

    public function getSharedCartId(): ?int
    {
        $value = $this->getData(self::SHARED_CART_ID);

        return $value === null ? null : (int) $value;
    }

    public function setSharedCartId(?int $sharedCartId): SharedCartInterface
    {
        return $this->setData(self::SHARED_CART_ID, $sharedCartId);
    }

    public function getStoreId(): int
    {
        return (int) $this->getData(self::STORE_ID);
    }

    public function setStoreId(int $storeId): SharedCartInterface
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    public function getQuoteId(): int
    {
        return (int) $this->getData(self::QUOTE_ID);
    }

    public function setQuoteId(int $quoteId): SharedCartInterface
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    public function getTokenHash(): string
    {
        return (string) $this->getData(self::TOKEN);
    }

    public function setTokenHash(string $tokenHash): SharedCartInterface
    {
        return $this->setData(self::TOKEN, $tokenHash);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData(self::CREATED_AT);

        return $value === null ? null : (string) $value;
    }

    public function setCreatedAt(?string $createdAt): SharedCartInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData(self::UPDATED_AT);

        return $value === null ? null : (string) $value;
    }

    public function setUpdatedAt(?string $updatedAt): SharedCartInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    public function getExpiresAt(): ?string
    {
        $value = $this->getData(self::EXPIRES_AT);

        return $value === null ? null : (string) $value;
    }

    public function setExpiresAt(?string $expiresAt): SharedCartInterface
    {
        return $this->setData(self::EXPIRES_AT, $expiresAt);
    }
}
