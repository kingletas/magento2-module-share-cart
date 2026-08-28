<?php
/**
 * EnumerationCostTest.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Test\Performance;

use Commerce\Foundation\Model\Security\TokenGenerator;
use Commerce\Foundation\Test\Support\BudgetAssertions;
use Commerce\ShareCart\Api\Data\SharedCartInterface;
use Commerce\ShareCart\Api\SharedCartRepositoryInterface;
use Commerce\ShareCart\Controller\Cart\Share;
use Commerce\ShareCart\Model\Cart\SharedCartRestorer;
use Commerce\ShareCart\Model\Config;
use Commerce\ShareCart\Model\Validator\TokenFormatValidator;
use Commerce\ShareCart\Test\Unit\Fake\ArrayScopeConfig;
use Commerce\ShareCart\Test\Unit\Fake\RecordingLogger;
use Commerce\ShareCart\Test\Unit\Fake\SnapshotQuote;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Phrase;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * What a stranger with a URL costs.
 */
final class EnumerationCostTest extends TestCase
{
    use BudgetAssertions;

    private const SECTION = 'commerce_sharecart';

    private int $lookups = 0;

    protected function setUp(): void
    {
        $this->lookups = 0;
    }

    /**
     * Too short, too long, not hex, empty - every one of them is decidable from
     * the string, and deciding it in PHP is free.
     */
    public function testAMalformedTokenIsRejectedWithoutAQuery(): void
    {
        $guesses = [
            '',
            'short',
            'not-hex-at-all-but-long-enough-to-look-plausible',
            str_repeat('a', 31),
            str_repeat('f', 4096),
            "0123456789abcdef' OR '1'='1",
        ];

        foreach ($guesses as $guess) {
            $this->controller($guess)->execute();
        }

        self::assertCostAtMost('database lookups for six malformed tokens', 0, $this->lookups);
    }

    /**
     * Stated as a scaling property, because a regression would make it one
     * query per guess.
     */
    public function testAFloodOfMalformedGuessesCostsNothingAtAnyRate(): void
    {
        self::assertConstantCost(
            'database lookups while rejecting malformed tokens',
            function (int $guesses): int {
                $this->lookups = 0;

                for ($i = 0; $i < $guesses; $i++) {
                    $controller = $this->controller('not-a-valid-token-' . $i);
                    $controller->execute();
                }

                return $this->lookups;
            },
            [1, 500]
        );
    }

    /**
     * This is the guess the format check cannot catch, and one query is the
     * honest price of answering it.
     */
    public function testAWellFormedButUnknownTokenCostsOneLookup(): void
    {
        $this->controller((new TokenGenerator())->generate())->execute();

        self::assertCostAtMost('one plausible guess', 1, $this->lookups);
    }

    /**
     * A store that has turned sharing off should not be answering questions
     * about which tokens it once issued.
     */
    public function testWithTheFeatureOffEvenAValidTokenIsNotLookedUp(): void
    {
        $this->controller((new TokenGenerator())->generate(), enabled: false)->execute();

        self::assertSame(0, $this->lookups);
    }

    /**
     * The string below is *valid hex*, so only the length bound can reject it -
     * and the bound is checked before the pattern runs.
     */
    public function testAnOversizedTokenIsRefusedOnItsLengthRatherThanScanned(): void
    {
        $validator = new TokenFormatValidator();

        self::assertFalse(
            $validator->isValid(str_repeat('f', 1_000_000)),
            'A megabyte of valid hex must be refused on length, before anything scans it.'
        );
        self::assertFalse($validator->isValid(str_repeat('f', 513)));
        self::assertTrue($validator->isValid(str_repeat('f', 512)), 'The bound must not reject a real token.');
    }

    private function controller(string $token = '', bool $enabled = true): Share
    {
        $settings = [
            self::SECTION . '/general/enabled' => $enabled ? '1' : '0',
            self::SECTION . '/general/lifetime_days' => '7',
        ];

        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnSelf();

        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);

        return new Share(
            $this->request($token),
            $redirectFactory,
            $this->createMock(MessageManagerInterface::class),
            $this->restorer(),
            new TokenFormatValidator(),
            new Config(new ArrayScopeConfig($settings), self::SECTION)
        );
    }

    private function request(string $token): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $name, $default = null) => $name === 'token' ? $token : $default
        );

        return $request;
    }

    /**
     * A restorer whose only route to the database is counted.
     */
    private function restorer(): SharedCartRestorer
    {
        $sharedCarts = $this->createMock(SharedCartRepositoryInterface::class);
        $sharedCarts->method('getByToken')->willReturnCallback(
            function (): SharedCartInterface {
                $this->lookups++;

                throw new NoSuchEntityException(new Phrase('That shared cart link is not valid.'));
            }
        );

        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willReturnCallback(static fn (): Quote => new SnapshotQuote());

        $cartFactory = $this->createMock(CartInterfaceFactory::class);
        $cartFactory->method('create')->willReturnCallback(static fn (): Quote => new SnapshotQuote());

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn(new DataObject(['id' => 1]));

        return new SharedCartRestorer(
            $session,
            $cartFactory,
            $this->createMock(CartRepositoryInterface::class),
            $sharedCarts,
            $storeManager,
            new RecordingLogger()
        );
    }
}
