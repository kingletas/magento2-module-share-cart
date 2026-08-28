<?php
/**
 * SharedCartSearchResultsTest.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Model\Repository;

use Commerce\ShareCart\Api\Data\SharedCartSearchResultsInterface;
use Commerce\ShareCart\Model\Repository\SharedCartSearchResults;
use Magento\Framework\Api\SearchResults;
use PHPUnit\Framework\TestCase;

class SharedCartSearchResultsTest extends TestCase
{
    /**
     * `getList()` declares a real return type, so the generic `SearchResults`
     * is a TypeError.
     */
    public function testItSatisfiesTheTypedReturnOfGetList(): void
    {
        $results = new SharedCartSearchResults();

        self::assertInstanceOf(SharedCartSearchResultsInterface::class, $results);
        self::assertInstanceOf(SearchResults::class, $results);
    }

    public function testItCarriesItemsAndATotalCountLikeAnySearchResult(): void
    {
        $results = new SharedCartSearchResults();
        $results->setItems(['a', 'b']);
        $results->setTotalCount(2);

        self::assertSame(['a', 'b'], $results->getItems());
        self::assertSame(2, $results->getTotalCount());
    }
}
