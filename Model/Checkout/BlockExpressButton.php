<?php
/**
 * BlockExpressButton.php
 *
 * @package     Commerce_ShareCart
 * @copyright   Copyright (c) the Commerce modules authors
 * @license     OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */
declare(strict_types=1);

namespace Commerce\ShareCart\Model\Checkout;

use Commerce\ShareCart\Api\Checkout\ExpressButtonInterface;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\LayoutInterface;

/**
 * Generic adapter that renders any layout block as an express button.
 */
class BlockExpressButton implements ExpressButtonInterface
{
    private ?BlockInterface $block = null;
    private bool $blockResolved = false;

    /**
     * @param string $code       Stable identifier for this button.
     * @param string $blockClass FQCN of the block to instantiate.
     * @param string $template   Template the block should render with.
     */
    public function __construct(
        private readonly LayoutInterface $layout,
        private readonly string $code,
        private readonly string $blockClass,
        private readonly string $template = '',
        private readonly int $sortOrder = 100
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function isAvailable(): bool
    {
        return $this->resolveBlock() !== null;
    }

    public function render(): string
    {
        $block = $this->resolveBlock();

        return $block === null ? '' : $block->toHtml();
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    private function resolveBlock(): ?BlockInterface
    {
        if ($this->blockResolved) {
            return $this->block;
        }

        $this->blockResolved = true;

        // The payment module may not be installed. class_exists is cheaper and
        // safer than letting the object manager raise.
        if (!class_exists($this->blockClass)) {
            return $this->block = null;
        }

        $block = $this->layout->createBlock($this->blockClass);

        if ($this->template !== '' && method_exists($block, 'setTemplate')) {
            $block->setTemplate($this->template);
        }

        return $this->block = $block;
    }
}
