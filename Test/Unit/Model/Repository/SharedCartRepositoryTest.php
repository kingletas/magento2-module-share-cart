<?php
/**
 * SharedCartRepositoryTest.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Model\Repository;

use Commerce\Foundation\Api\TokenGeneratorInterface;
use Commerce\Foundation\Model\Repository\SearchResultBuilder;
use Commerce\ShareCart\Api\Data\SharedCartInterface;
use Commerce\ShareCart\Api\Data\SharedCartInterfaceFactory;
use Commerce\ShareCart\Api\Data\SharedCartSearchResultsInterface;
use Commerce\ShareCart\Api\Data\SharedCartSearchResultsInterfaceFactory;
use Commerce\ShareCart\Model\Repository\SharedCartRepository;
use Commerce\ShareCart\Model\ResourceModel\SharedCart as SharedCartResource;
use Commerce\ShareCart\Model\ResourceModel\SharedCart\Collection;
use Commerce\ShareCart\Model\ResourceModel\SharedCart\CollectionFactory;
use Commerce\ShareCart\Model\SharedCart;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SharedCartRepositoryTest extends TestCase
{
    private const NOW = '2026-08-26 12:00:00';

    /** @var array<string, array<string, mixed>> Stored rows, keyed by "field:value". */
    private array $rows = [];

    /** @var array<int, array{field: string, value: mixed}> */
    private array $loads = [];

    /** @var SharedCartInterface[] */
    private array $saved = [];

    /** @var SharedCartInterface[] */
    private array $deleted = [];

    private SharedCartResource&MockObject $resource;

    protected function setUp(): void
    {
        $this->rows = [];
        $this->loads = [];
        $this->saved = [];
        $this->deleted = [];

        $this->resource = $this->createMock(SharedCartResource::class);
        $this->resource->method('load')->willReturnCallback(
            function ($entity, $value, $field = null): void {
                $field ??= SharedCartInterface::SHARED_CART_ID;
                $this->loads[] = ['field' => $field, 'value' => $value];

                foreach ($this->rows[$field . ':' . $value] ?? [] as $key => $data) {
                    $entity->setData($key, $data);
                }
            }
        );
        $this->resource->method('save')->willReturnCallback(
            function ($entity) {
                $this->saved[] = $entity;

                return $this->resource;
            }
        );
        $this->resource->method('delete')->willReturnCallback(
            function ($entity) {
                $this->deleted[] = $entity;

                return $this->resource;
            }
        );
        $this->resource->method('deleteExpired')->willReturn(4);
    }

    public function testAnEntityIsLoadedById(): void
    {
        $this->row(SharedCartInterface::SHARED_CART_ID, 5, ['id' => 5, 'quote_id' => 77]);

        $this->assertSame(5, $this->repository()->getById(5)->getSharedCartId());
    }

    public function testAnUnknownIdIsNoSuchEntity(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $this->repository()->getById(404);
    }

    public function testAnEntityIsLoadedByItsQuoteId(): void
    {
        $this->row(SharedCartInterface::QUOTE_ID, 77, ['id' => 5, 'quote_id' => 77]);

        $this->assertSame(77, $this->repository()->getByQuoteId(77)->getQuoteId());
        $this->assertSame(SharedCartInterface::QUOTE_ID, $this->loads[0]['field']);
    }

    public function testAnUnknownQuoteIdIsNoSuchEntity(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $this->repository()->getByQuoteId(404);
    }

    /**
     * The token is a bearer credential: only its digest is ever stored, so the
     * lookup has to be by digest too.
     */
    public function testATokenIsLookedUpByItsDigestRatherThanItsPlaintext(): void
    {
        $this->row(SharedCartInterface::TOKEN, 'hash-of-tok3n', ['id' => 5]);

        $this->repository()->getByToken('tok3n');

        $this->assertSame(SharedCartInterface::TOKEN, $this->loads[0]['field']);
        $this->assertSame('hash-of-tok3n', $this->loads[0]['value']);
    }

    /**
     * The share flow reads the token twice per request - once validating, once
     * rebuilding the quote.
     */
    public function testARepeatedTokenLookupHitsTheDatabaseOnce(): void
    {
        $this->row(SharedCartInterface::TOKEN, 'hash-of-tok3n', ['id' => 5]);
        $repository = $this->repository();

        $first = $repository->getByToken('tok3n');
        $second = $repository->getByToken('tok3n');

        $this->assertSame($first, $second);
        $this->assertCount(1, $this->loads);
    }

    /**
     * "No such token" and "expired token" have to be indistinguishable, or the
     * endpoint tells an attacker which tokens once existed.
     */
    public function testAnUnknownAndAnExpiredTokenRaiseTheSameMessage(): void
    {
        $this->row(SharedCartInterface::TOKEN, 'hash-of-old', ['id' => 5, 'expires_at' => '2026-08-01 00:00:00']);

        $messages = [];

        foreach (['unknown', 'old'] as $token) {
            try {
                $this->repository()->getByToken($token);
                $this->fail('Expected the lookup to fail for "' . $token . '".');
            } catch (NoSuchEntityException $e) {
                $messages[] = $e->getMessage();
            }
        }

        $this->assertSame($messages[0], $messages[1]);
    }

    public function testALinkWhoseExpiryHasNotPassedIsStillValid(): void
    {
        $this->row(SharedCartInterface::TOKEN, 'hash-of-tok3n', ['id' => 5, 'expires_at' => '2026-09-01 00:00:00']);

        $this->assertSame(5, $this->repository()->getByToken('tok3n')->getSharedCartId());
    }

    /**
     * A null expiry is the documented "never expires" setting, and must not be
     * read as "expired at the epoch".
     */
    public function testALinkWithNoExpiryNeverExpires(): void
    {
        $this->row(SharedCartInterface::TOKEN, 'hash-of-tok3n', ['id' => 5, 'expires_at' => null]);

        $this->assertSame(5, $this->repository()->getByToken('tok3n')->getSharedCartId());
    }

    /**
     * An expired link must not be memoised as valid either - the identity map
     * is only ever allowed to hold entities that passed the expiry check.
     */
    public function testAnExpiredLinkIsRefusedOnEveryAttempt(): void
    {
        $this->row(SharedCartInterface::TOKEN, 'hash-of-tok3n', ['id' => 5, 'expires_at' => '2026-08-01 00:00:00']);
        $repository = $this->repository();
        $refusals = 0;

        foreach ([1, 2] as $attempt) {
            try {
                $repository->getByToken('tok3n');
                $this->fail('Expected attempt ' . $attempt . ' to be refused.');
            } catch (NoSuchEntityException) {
                $refusals++;
            }
        }

        $this->assertSame(2, $refusals);
    }

    public function testSavingReturnsTheEntityItPersisted(): void
    {
        $entity = $this->entity();

        $this->assertSame($entity, $this->repository()->save($entity));
        $this->assertSame([$entity], $this->saved);
    }

    /**
     * A driver-level failure surfaces as CouldNotSaveException rather than a
     * raw PDOException.
     */
    public function testAFailedSaveIsWrappedInTheContractsException(): void
    {
        $this->resource = $this->createMock(SharedCartResource::class);
        $this->resource->method('save')->willThrowException(new RuntimeException('duplicate key'));

        $this->expectException(CouldNotSaveException::class);

        $this->repository()->save($this->entity());
    }

    public function testDeletingRemovesTheEntity(): void
    {
        $entity = $this->entity();

        $this->repository()->delete($entity);

        $this->assertSame([$entity], $this->deleted);
    }

    public function testAFailedDeleteIsWrappedInTheContractsException(): void
    {
        $this->resource = $this->createMock(SharedCartResource::class);
        $this->resource->method('delete')->willThrowException(new RuntimeException('foreign key'));

        $this->expectException(CouldNotDeleteException::class);

        $this->repository()->delete($this->entity());
    }

    public function testDeletingByIdLoadsTheRowFirst(): void
    {
        $this->row(SharedCartInterface::SHARED_CART_ID, 5, ['id' => 5]);

        $this->repository()->deleteById(5);

        $this->assertCount(1, $this->deleted);
        $this->assertSame(5, $this->deleted[0]->getSharedCartId());
    }

    /**
     * Deleting a row that does not exist fails rather than reporting success.
     */
    public function testDeletingAnIdThatDoesNotExistFails(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $this->repository()->deleteById(404);
    }

    public function testPurgingExpiredUsesTheCurrentGmtTime(): void
    {
        $this->resource = $this->createMock(SharedCartResource::class);
        $this->resource->expects($this->once())
            ->method('deleteExpired')
            ->with(self::NOW)
            ->willReturn(4);

        $this->assertSame(4, $this->repository()->purgeExpired());
    }

    public function testTheListGoesThroughTheSharedSearchResultBuilder(): void
    {
        $criteria = $this->createMock(SearchCriteriaInterface::class);
        $results = $this->createMock(SharedCartSearchResultsInterface::class);

        $builder = $this->createMock(SearchResultBuilder::class);
        $builder->expects($this->once())->method('build')->willReturn($results);

        $this->assertSame($results, $this->repository($builder)->getList($criteria));
    }

    /**
     * The real entity, because the resource model's `load()` and `save()` are
     * typed against it.
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

    /**
     * @param array<string, mixed> $data
     */
    private function row(string $field, mixed $value, array $data): void
    {
        $mapped = [];

        foreach ($data as $key => $item) {
            $mapped[$key === 'id' ? SharedCartInterface::SHARED_CART_ID : $key] = $item;
        }

        $this->rows[$field . ':' . $value] = $mapped;
    }

    private function repository(?SearchResultBuilder $builder = null): SharedCartRepository
    {
        $entityFactory = $this->createMock(SharedCartInterfaceFactory::class);
        $entityFactory->method('create')->willReturnCallback(fn (): SharedCartInterface => $this->entity());

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($this->createMock(Collection::class));

        $searchResultsFactory = $this->createMock(SharedCartSearchResultsInterfaceFactory::class);
        $searchResultsFactory->method('create')
            ->willReturn($this->createMock(SharedCartSearchResultsInterface::class));

        $tokenGenerator = $this->createMock(TokenGeneratorInterface::class);
        $tokenGenerator->method('hash')->willReturnCallback(static fn (string $t): string => 'hash-of-' . $t);

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturn(self::NOW);

        return new SharedCartRepository(
            $this->resource,
            $entityFactory,
            $collectionFactory,
            $builder ?? $this->createMock(SearchResultBuilder::class),
            $searchResultsFactory,
            $tokenGenerator,
            $dateTime
        );
    }
}
