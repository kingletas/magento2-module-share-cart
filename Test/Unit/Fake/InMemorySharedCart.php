<?php
/**
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Fake;

use Commerce\ShareCart\Api\Data\SharedCartInterface;

/**
 * The entity, in an array.
 */
final class InMemorySharedCart implements SharedCartInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function getSharedCartId(): ?int
    {
        return isset($this->data['id']) ? (int) $this->data['id'] : null;
    }

    public function setSharedCartId(?int $sharedCartId): SharedCartInterface
    {
        $this->data['id'] = $sharedCartId;

        return $this;
    }

    public function getStoreId(): int
    {
        return (int) ($this->data['store_id'] ?? 0);
    }

    public function setStoreId(int $storeId): SharedCartInterface
    {
        $this->data['store_id'] = $storeId;

        return $this;
    }

    public function getQuoteId(): int
    {
        return (int) ($this->data['quote_id'] ?? 0);
    }

    public function setQuoteId(int $quoteId): SharedCartInterface
    {
        $this->data['quote_id'] = $quoteId;

        return $this;
    }

    public function getTokenHash(): string
    {
        return (string) ($this->data['token_hash'] ?? '');
    }

    public function setTokenHash(string $tokenHash): SharedCartInterface
    {
        $this->data['token_hash'] = $tokenHash;

        return $this;
    }

    public function getCreatedAt(): ?string
    {
        return $this->data['created_at'] ?? null;
    }

    public function setCreatedAt(?string $createdAt): SharedCartInterface
    {
        $this->data['created_at'] = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->data['updated_at'] ?? null;
    }

    public function setUpdatedAt(?string $updatedAt): SharedCartInterface
    {
        $this->data['updated_at'] = $updatedAt;

        return $this;
    }

    public function getExpiresAt(): ?string
    {
        return $this->data['expires_at'] ?? null;
    }

    public function setExpiresAt(?string $expiresAt): SharedCartInterface
    {
        $this->data['expires_at'] = $expiresAt;

        return $this;
    }
}
