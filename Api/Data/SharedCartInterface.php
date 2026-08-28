<?php
/**
 * SharedCartInterface.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Api\Data;

/**
 * A shareable snapshot of a cart.
 */
interface SharedCartInterface
{
    public const string SHARED_CART_ID = 'shared_cart_id';
    public const string STORE_ID = 'store_id';
    public const string QUOTE_ID = 'quote_id';
    public const string TOKEN = 'token';
    public const string CREATED_AT = 'created_at';
    public const string UPDATED_AT = 'updated_at';
    public const string EXPIRES_AT = 'expires_at';

    public function getSharedCartId(): ?int;

    public function setSharedCartId(?int $sharedCartId): self;

    public function getStoreId(): int;

    public function setStoreId(int $storeId): self;

    public function getQuoteId(): int;

    public function setQuoteId(int $quoteId): self;

    public function getTokenHash(): string;

    public function setTokenHash(string $tokenHash): self;

    public function getCreatedAt(): ?string;

    public function setCreatedAt(?string $createdAt): self;

    public function getUpdatedAt(): ?string;

    public function setUpdatedAt(?string $updatedAt): self;

    /**
     * @return string|null Null means the link never expires.
     */
    public function getExpiresAt(): ?string;

    public function setExpiresAt(?string $expiresAt): self;
}
