<?php
/**
 * @package   Commerce_ShareCart
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\ShareCart\Model\ResourceModel\SharedCart;

use Commerce\ShareCart\Api\Data\SharedCartInterface;
use Commerce\ShareCart\Model\ResourceModel\SharedCart as SharedCartResource;
use Commerce\ShareCart\Model\SharedCart;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Magento requires the _construct() initialiser, which trips PHPMD naming.
 *
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class Collection extends AbstractCollection
{
    /**
     * Set through the setter rather than by redeclaring the property.
     */
    protected function _construct(): void
    {
        $this->_setIdFieldName(SharedCartInterface::SHARED_CART_ID);
        $this->_init(SharedCart::class, SharedCartResource::class);
    }
}
