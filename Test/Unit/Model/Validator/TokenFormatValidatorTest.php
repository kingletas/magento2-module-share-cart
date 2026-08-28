<?php
/**
 * TokenFormatValidatorTest.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Model\Validator;

use Commerce\ShareCart\Model\Validator\TokenFormatValidator;
use PHPUnit\Framework\TestCase;

class TokenFormatValidatorTest extends TestCase
{
    private TokenFormatValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new TokenFormatValidator(16);
    }

    public function testAcceptsAWellFormedToken(): void
    {
        self::assertTrue($this->validator->isValid(str_repeat('a1', 16)));
    }

    /**
     * `!preg_match(...)` treats the function's `false` error return as "valid",
     * so a backtrack-limit overflow admits the input.
     */
    public function testRejectsNonHexCharacters(): void
    {
        self::assertFalse($this->validator->isValid(str_repeat('z', 32)));
        self::assertFalse($this->validator->isValid(str_repeat('A', 32)));
        self::assertFalse($this->validator->isValid('../../etc/passwd'));
    }

    public function testRejectsTokensShorterThanTheConfiguredEntropy(): void
    {
        self::assertFalse($this->validator->isValid(str_repeat('a', 31)));
        self::assertFalse($this->validator->isValid(''));
    }

    /**
     * An attacker-supplied megabyte string should be rejected on length before
     * a regex is ever run over it.
     */
    public function testRejectsAbsurdlyLongInputWithoutScanningIt(): void
    {
        self::assertFalse($this->validator->isValid(str_repeat('a', 100000)));
    }
}
