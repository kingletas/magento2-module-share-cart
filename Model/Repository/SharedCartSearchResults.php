<?php
/**
 * @package   Commerce_ShareCart
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\ShareCart\Model\Repository;

use Commerce\ShareCart\Api\Data\SharedCartSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

/**
 * Typed search results for shared carts.
 */
class SharedCartSearchResults extends SearchResults implements SharedCartSearchResultsInterface
{
}
