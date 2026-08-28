<?php
/**
 * SharedCartTest.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Model;

use Commerce\ShareCart\Api\Data\SharedCartInterface;
use Commerce\ShareCart\Model\ResourceModel\SharedCart as SharedCartResource;
use Commerce\ShareCart\Model\SharedCart;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;

class SharedCartTest extends TestCase
{
    /**
     * Read off the declared name, because `getResourceName()` answers with
     * whatever was injected.
     */
    public function testTheEntityDeclaresItsOwnResourceModel(): void
    {
        $declared = (new \ReflectionProperty(SharedCart::class, '_resourceName'))->getValue($this->entity());

        $this->assertSame(SharedCartResource::class, $declared);
    }

    public function testTheEntityIsKeyedOnTheSharedCartId(): void
    {
        $this->assertSame(SharedCartInterface::SHARED_CART_ID, $this->entity()->getIdFieldName());
    }

    public function testEveryFieldRoundTripsThroughItsSetter(): void
    {
        $entity = $this->entity()
            ->setSharedCartId(5)
            ->setStoreId(1)
            ->setQuoteId(99)
            ->setTokenHash('abc123')
            ->setCreatedAt('2026-01-01 00:00:00')
            ->setUpdatedAt('2026-01-02 00:00:00')
            ->setExpiresAt('2026-02-01 00:00:00');

        $this->assertSame(5, $entity->getSharedCartId());
        $this->assertSame(1, $entity->getStoreId());
        $this->assertSame(99, $entity->getQuoteId());
        $this->assertSame('abc123', $entity->getTokenHash());
        $this->assertSame('2026-01-01 00:00:00', $entity->getCreatedAt());
        $this->assertSame('2026-01-02 00:00:00', $entity->getUpdatedAt());
        $this->assertSame('2026-02-01 00:00:00', $entity->getExpiresAt());
    }

    public function testTheSettersAreFluentAndReturnTheEntityItself(): void
    {
        $entity = $this->entity();

        $this->assertSame($entity, $entity->setStoreId(1));
        $this->assertSame($entity, $entity->setQuoteId(1));
        $this->assertSame($entity, $entity->setTokenHash('x'));
    }

    /**
     * The database hands back strings.
     */
    public function testTheNumericGettersCoerceWhatTheDatabaseHandsBack(): void
    {
        $entity = $this->entity();
        $entity->setData(SharedCartInterface::STORE_ID, '1');
        $entity->setData(SharedCartInterface::QUOTE_ID, '99');
        $entity->setData(SharedCartInterface::SHARED_CART_ID, '5');

        $this->assertSame(1, $entity->getStoreId());
        $this->assertSame(99, $entity->getQuoteId());
        $this->assertSame(5, $entity->getSharedCartId());
    }

    public function testTheTokenHashCoercesToAString(): void
    {
        $entity = $this->entity();
        $entity->setData(SharedCartInterface::TOKEN, 12345);

        $this->assertSame('12345', $entity->getTokenHash());
    }

    /**
     * A never-saved entity has no id, and the repository tells "loaded" from
     * "not found" by exactly that null.
     */
    public function testAnUnsavedEntityHasNoIdRatherThanZero(): void
    {
        $this->assertNull($this->entity()->getSharedCartId());
    }

    /**
     * Null expiry means never expires, which the repository's expiry check
     * branches on.
     */
    public function testAnAbsentTimestampStaysNull(): void
    {
        $entity = $this->entity();

        $this->assertNull($entity->getExpiresAt());
        $this->assertNull($entity->getCreatedAt());
        $this->assertNull($entity->getUpdatedAt());
    }

    public function testTheStoreAndQuoteIdsDefaultToZeroWhenUnset(): void
    {
        $entity = $this->entity();

        $this->assertSame(0, $entity->getStoreId());
        $this->assertSame(0, $entity->getQuoteId());
        $this->assertSame('', $entity->getTokenHash());
    }

    /**
     * The resource is passed in, because `_init()` asks it for the id field
     * name.
     */
    private function entity(): SharedCart
    {
        $resource = $this->createMock(SharedCartResource::class);
        $resource->method('getIdFieldName')->willReturn(SharedCartInterface::SHARED_CART_ID);

        return new SharedCart(
            $this->createMock(Context::class),
            $this->createMock(Registry::class),
            $resource
        );
    }
}
