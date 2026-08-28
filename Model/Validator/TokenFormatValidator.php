<?php
/**
 * TokenFormatValidator.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Model\Validator;

use Commerce\Foundation\Model\Security\TokenGenerator;

/**
 * Cheap structural check on a share token, before any database work.
 */
class TokenFormatValidator
{
    /**
     * Tokens are hex from TokenGenerator: 2 characters per byte.
     */
    private const string PATTERN = '/^[a-f0-9]+$/';

    public function __construct(
        private readonly int $minBytes = TokenGenerator::MIN_BYTES
    ) {
    }

    public function isValid(string $token): bool
    {
        $expectedLength = $this->minBytes * 2;

        if (strlen($token) < $expectedLength) {
            return false;
        }

        // Bound the input before running a regex over it: an attacker-supplied
        // megabyte string should be rejected on length, not scanned.
        if (strlen($token) > 512) {
            return false;
        }

        return preg_match(self::PATTERN, $token) === 1;
    }
}
