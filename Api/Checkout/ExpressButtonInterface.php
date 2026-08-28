<?php
/**
 * ExpressButtonInterface.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Api\Checkout;

/**
 * One express-checkout button that may be rendered beside the share control.
 */
interface ExpressButtonInterface
{
    public function getCode(): string;

    /**
     * Whether this button should render for the current store and cart.
     */
    public function isAvailable(): bool;

    /**
     * Render the button.
     *
     * @return string HTML, already escaped by the underlying block/template.
     */
    public function render(): string;

    public function getSortOrder(): int;
}
