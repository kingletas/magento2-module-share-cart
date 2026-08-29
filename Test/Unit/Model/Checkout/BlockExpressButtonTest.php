<?php
/**
 * @package   Commerce_ShareCart
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\ShareCart\Test\Unit\Model\Checkout;

use Commerce\ShareCart\Api\Checkout\ExpressButtonInterface;
use Commerce\ShareCart\Model\Checkout\BlockExpressButton;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BlockExpressButtonTest extends TestCase
{
    private LayoutInterface&MockObject $layout;

    protected function setUp(): void
    {
        $this->layout = $this->createMock(LayoutInterface::class);
    }

    public function testTheCodeAndSortOrderComeStraightFromConfiguration(): void
    {
        $button = $this->button(['code' => 'paypal', 'sortOrder' => 10]);

        $this->assertInstanceOf(ExpressButtonInterface::class, $button);
        $this->assertSame('paypal', $button->getCode());
        $this->assertSame(10, $button->getSortOrder());
    }

    public function testTheConfiguredBlockIsRendered(): void
    {
        $block = $this->createMock(BlockInterface::class);
        $block->method('toHtml')->willReturn('<button>Pay</button>');
        $this->layout->expects($this->once())
            ->method('createBlock')
            ->with(Template::class)
            ->willReturn($block);

        $this->assertSame('<button>Pay</button>', $this->button()->render());
    }

    public function testTheConfiguredTemplateIsAppliedToTheBlock(): void
    {
        $block = $this->createMock(Template::class);
        $block->expects($this->once())->method('setTemplate')->with('Acme_Pay::button.phtml');
        $block->method('toHtml')->willReturn('');
        $this->layout->method('createBlock')->willReturn($block);

        $this->button(['template' => 'Acme_Pay::button.phtml'])->render();
    }

    /**
     * A block that renders itself from layout XML needs no template argument,
     * and calling setTemplate('') would blank it.
     */
    public function testNoTemplateIsSetWhenNoneIsConfigured(): void
    {
        $block = $this->createMock(Template::class);
        $block->expects($this->never())->method('setTemplate');
        $block->method('toHtml')->willReturn('');
        $this->layout->method('createBlock')->willReturn($block);

        $this->button(['template' => ''])->render();
    }

    /**
     * Not every block class has setTemplate.
     */
    public function testABlockWithoutSetTemplateIsRenderedAnyway(): void
    {
        $block = $this->createMock(BlockInterface::class);
        $block->method('toHtml')->willReturn('<button>Pay</button>');
        $this->layout->method('createBlock')->willReturn($block);

        $this->assertSame('<button>Pay</button>', $this->button(['template' => 'x.phtml'])->render());
    }

    public function testAConfiguredAndInstalledBlockIsAvailable(): void
    {
        $this->layout->method('createBlock')->willReturn($this->createMock(BlockInterface::class));

        $this->assertTrue($this->button()->isAvailable());
    }

    /**
     * A store that uninstalls its payment module leaves the virtualType behind
     * in di.xml.
     */
    public function testAnUninstalledPaymentBlockIsUnavailableRatherThanFatal(): void
    {
        $this->layout->expects($this->never())->method('createBlock');
        $button = $this->button(['blockClass' => 'Acme\\Uninstalled\\Payment\\Block\\Button']);

        $this->assertFalse($button->isAvailable());
        $this->assertSame('', $button->render());
    }

    /**
     * The cart page asks availability first and renders second.
     */
    public function testTheBlockIsBuiltOnceHoweverOftenItIsAskedFor(): void
    {
        $block = $this->createMock(BlockInterface::class);
        $block->method('toHtml')->willReturn('x');
        $this->layout->expects($this->once())->method('createBlock')->willReturn($block);

        $button = $this->button();
        $button->isAvailable();
        $button->render();
        $button->isAvailable();
    }

    /**
     * The negative answer is memoised too, or a missing payment class costs a
     * `class_exists` lookup per call for a button that will never render.
     */
    public function testTheMissingBlockAnswerIsMemoisedToo(): void
    {
        $this->layout->expects($this->never())->method('createBlock');
        $button = $this->button(['blockClass' => 'Acme\\Uninstalled\\Payment\\Block\\Button']);

        $this->assertFalse($button->isAvailable());
        $this->assertFalse($button->isAvailable());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function button(array $overrides = []): BlockExpressButton
    {
        $config = $overrides + [
            'code' => 'express',
            'blockClass' => Template::class,
            'template' => '',
            'sortOrder' => 100,
        ];

        return new BlockExpressButton(
            $this->layout,
            $config['code'],
            $config['blockClass'],
            $config['template'],
            $config['sortOrder']
        );
    }
}
