<?php
/**
 * SharedCartSearchResultsInterface.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface SharedCartSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return SharedCartInterface[]
     */
    public function getItems();

    /**
     * @param SharedCartInterface[] $items
     *
     * @return $this
     */
    public function setItems(array $items);
}
