<?php
/**
 * RestoreResult.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Model\Cart;

use InvalidArgumentException;
use Magento\Framework\Phrase;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Outcome of a shared-cart restore.
 */
class RestoreResult
{
    public readonly ?Phrase $message;

    public function __construct(
        public readonly RestoreOutcome $outcome,
        public readonly ?CartInterface $quote = null
    ) {
        if ($this->outcome === RestoreOutcome::Restored && $this->quote === null) {
            // Enforced here: the controller reads `quote` whenever the outcome
            // is a success.
            throw new InvalidArgumentException('A restored cart result must carry the quote it restored.');
        }

        $this->message = $this->outcome->message();
    }

    public function isSuccess(): bool
    {
        return $this->outcome->isSuccess();
    }
}
