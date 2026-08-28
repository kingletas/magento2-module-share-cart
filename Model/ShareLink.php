<?php
/**
 * ShareLink.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Model;

use InvalidArgumentException;
use Magento\Framework\Phrase;

/**
 * The outcome of issuing a share link.
 */
class ShareLink
{
    public function __construct(
        public readonly bool $isSuccess,
        public readonly ?string $token = null,
        public readonly ?string $url = null,
        public readonly ?Phrase $message = null
    ) {
        if ($this->isSuccess && ($this->token === null || $this->url === null)) {
            throw new InvalidArgumentException('A successful share link needs both a token and a URL.');
        }

        if (!$this->isSuccess && $this->message === null) {
            // A failure with no message reaches the shopper as an empty error
            // box.
            throw new InvalidArgumentException('A failed share link needs a message.');
        }
    }
}
