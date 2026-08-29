<?php
/**
 * @package   Commerce_ShareCart
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Model\ResourceModel\SharedCart;

use Commerce\ShareCart\Api\Data\SharedCartInterface;
use Commerce\ShareCart\Model\ResourceModel\SharedCart as SharedCartResource;
use Commerce\ShareCart\Model\ResourceModel\SharedCart\Collection;
use Commerce\ShareCart\Model\SharedCart;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * The collection's real constructor builds a SELECT through the object manager,
 * which a unit test does not have.
 */
class CollectionTest extends TestCase
{
    public function testTheCollectionIsWiredToTheEntityAndItsResource(): void
    {
        $collection = $this->collection();

        $this->assertSame(SharedCart::class, $collection->getModelName());
        $this->assertSame(SharedCartResource::class, $collection->getResourceModelName());
    }

    /**
     * Set through the setter: the parent declares `$_idFieldName` untyped.
     */
    public function testTheIdFieldIsSetThroughTheSetter(): void
    {
        $this->assertSame(SharedCartInterface::SHARED_CART_ID, $this->collection()->getIdFieldName());
    }

    /**
     * The default is `id`, which is not this table's key.
     */
    public function testTheIdFieldIsNotTheFrameworkDefault(): void
    {
        $this->assertNotSame('id', $this->collection()->getIdFieldName());
    }

    private function collection(): Collection
    {
        $collection = (new ReflectionClass(Collection::class))->newInstanceWithoutConstructor();
        (new ReflectionMethod($collection, '_construct'))->invoke($collection);

        return $collection;
    }
}
