<?php
/**
 * @package   Commerce_ShareCart
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\ShareCart\Model\Cart;

use Magento\Framework\Phrase;

/**
 * Why a shared-cart restore ended the way it did.
 */
enum RestoreOutcome: string
{
    case Restored = 'restored';
    case NotFound = 'not_found';
    case WrongStore = 'wrong_store';
    case Failed = 'failed';

    public function isSuccess(): bool
    {
        return $this === self::Restored;
    }

    /**
     * What the shopper is told, or null when there is nothing to apologise for.
     */
    public function message(): ?Phrase
    {
        return match ($this) {
            self::Restored => null,
            self::NotFound => __('That shared cart link is no longer valid.'),
            self::WrongStore => __('That shared cart link belongs to a different store.'),
            self::Failed => __('We could not open that shared cart. Please try again.'),
        };
    }
}
