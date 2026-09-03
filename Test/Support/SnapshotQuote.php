<?php
/**
 * @package   Commerce_ShareCart
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\ShareCart\Test\Support;

use Magento\Quote\Model\Quote;

/**
 * The quote the issuer builds its snapshot into.
 *
 * @SuppressWarnings("PHPMD.MissingConstructor")
 */
class SnapshotQuote extends Quote
{
    /** @var Quote[] */
    public array $merged = [];

    public bool $totalsCollected = false;

    /**
     * Untyped because `AbstractModel` declares it untyped, and redeclaring an
     * untyped parent property with a type is a fatal.
     *
     * @var array<string, mixed>
     */
    protected $_data = [];

    public function __construct()
    {
    }

    public function merge(Quote $quote)
    {
        $this->merged[] = $quote;

        return $this;
    }

    /**
     * The real one runs every total collector against a live store, a shipping
     * address and a price model.
     */
    public function collectTotals()
    {
        $this->totalsCollected = true;

        return $this;
    }
}
