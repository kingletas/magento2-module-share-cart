<?php
/**
 * @package   Commerce_ShareCart
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\ShareCart\Model\ResourceModel;

use Commerce\ShareCart\Api\Data\SharedCartInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Magento requires the _construct() initialiser, which trips PHPMD naming.
 *
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 */
class SharedCart extends AbstractDb
{
    public const string TABLE_NAME = 'commerce_shared_cart';

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, SharedCartInterface::SHARED_CART_ID);
    }

    /**
     * Delete every link whose expiry has passed.
     *
     * @return int Rows removed.
     */
    public function deleteExpired(string $now): int
    {
        $connection = $this->getConnection();

        return (int) $connection->delete(
            $this->getMainTable(),
            [
                'expires_at IS NOT NULL',
                'expires_at < ?' => $now,
            ]
        );
    }
}
