<?php
/**
 * SharedCartTest.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Model\ResourceModel;

use Commerce\ShareCart\Api\Data\SharedCartInterface;
use Commerce\ShareCart\Model\ResourceModel\SharedCart;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class SharedCartTest extends TestCase
{
    /** @var array<int, array{table: string, where: array<string, mixed>|string[]}> */
    private array $deletes = [];

    private AdapterInterface&MockObject $connection;

    protected function setUp(): void
    {
        $this->deletes = [];
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('delete')->willReturnCallback(
            function (string $table, $where = ''): int {
                $this->deletes[] = ['table' => $table, 'where' => $where];

                return 3;
            }
        );
    }

    /**
     * Read off the declared names, because `getMainTable()` needs a connection
     * a unit test has not.
     */
    public function testTheResourceIsWiredToItsTableAndKey(): void
    {
        $resource = (new ReflectionClass(SharedCart::class))->newInstanceWithoutConstructor();
        (new ReflectionMethod($resource, '_construct'))->invoke($resource);

        self::assertSame(
            SharedCart::TABLE_NAME,
            (new \ReflectionProperty(SharedCart::class, '_mainTable'))->getValue($resource)
        );
        self::assertSame(SharedCartInterface::SHARED_CART_ID, $resource->getIdFieldName());
    }

    /**
     * One set-based DELETE rather than a collection walked in PHP.
     */
    public function testExpiredLinksAreRemovedInASingleStatement(): void
    {
        $this->resource()->deleteExpired('2026-08-26 12:00:00');

        self::assertCount(1, $this->deletes);
        self::assertSame(SharedCart::TABLE_NAME, $this->deletes[0]['table']);
    }

    public function testTheCutoffIsBoundRatherThanInterpolated(): void
    {
        $this->resource()->deleteExpired('2026-08-26 12:00:00');

        self::assertSame('2026-08-26 12:00:00', $this->deletes[0]['where']['expires_at < ?']);
    }

    /**
     * A null expiry means "never expires".
     */
    public function testLinksWithNoExpiryAreExcluded(): void
    {
        $this->resource()->deleteExpired('2026-08-26 12:00:00');

        self::assertContains('expires_at IS NOT NULL', $this->deletes[0]['where']);
    }

    public function testTheNumberOfRemovedRowsIsReturnedAsAnInteger(): void
    {
        self::assertSame(3, $this->resource()->deleteExpired('2026-08-26 12:00:00'));
    }

    /**
     * Built with only the two seams `deleteExpired` uses replaced, so the
     * method is the shipped one.
     */
    private function resource(): SharedCart&MockObject
    {
        $resource = $this->getMockBuilder(SharedCart::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getMainTable'])
            ->getMock();
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getMainTable')->willReturn(SharedCart::TABLE_NAME);

        return $resource;
    }
}
