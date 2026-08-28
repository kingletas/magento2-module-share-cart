<?php
/**
 * ExpressButtonPool.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Model\Checkout;

use Commerce\ShareCart\Api\Checkout\ExpressButtonInterface;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Registry of express-checkout buttons, populated from di.xml.
 */
class ExpressButtonPool
{
    /** @var ExpressButtonInterface[]|null */
    private ?array $sorted = null;

    /**
     * @param array<string, ExpressButtonInterface> $buttons
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly array $buttons = []
    ) {
        foreach ($this->buttons as $key => $button) {
            if (!$button instanceof ExpressButtonInterface) {
                throw new InvalidArgumentException(sprintf(
                    'Express button "%s" must implement %s, got %s.',
                    $key,
                    ExpressButtonInterface::class,
                    get_debug_type($button)
                ));
            }
        }
    }

    /**
     * Buttons that are available right now, in render order.
     *
     * @return ExpressButtonInterface[]
     */
    public function getAvailable(): array
    {
        return array_values(array_filter(
            $this->getSorted(),
            function (ExpressButtonInterface $button): bool {
                try {
                    return $button->isAvailable();
                } catch (Throwable $e) {
                    // One broken payment integration must not take the cart
                    // page down with it.
                    $this->logger->warning(
                        sprintf('Express button "%s" failed its availability check.', $button->getCode()),
                        ['exception' => $e]
                    );

                    return false;
                }
            }
        ));
    }

    /**
     * @return ExpressButtonInterface[]
     */
    private function getSorted(): array
    {
        if ($this->sorted !== null) {
            return $this->sorted;
        }

        $buttons = $this->buttons;

        uasort(
            $buttons,
            static fn (ExpressButtonInterface $a, ExpressButtonInterface $b): int
                => $a->getSortOrder() <=> $b->getSortOrder()
        );

        return $this->sorted = $buttons;
    }
}
