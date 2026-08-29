<?php
/**
 * @package   Commerce_ShareCart
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\ShareCart\ViewModel;

use Commerce\ShareCart\Api\Checkout\ExpressButtonInterface;
use Commerce\ShareCart\Model\Checkout\ExpressButtonPool;
use Commerce\ShareCart\Model\Config;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * View model backing the share control on the cart page.
 */
class ShareCartButton implements ArgumentInterface
{
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly Json $json,
        private readonly ExpressButtonPool $expressButtonPool,
        private readonly Config $config
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    public function getJsonConfig(): string
    {
        return $this->json->serialize([
            'generateUrl' => $this->urlBuilder->getUrl('sharecart/cart/generate', ['_secure' => true]),
        ]);
    }

    /**
     * Express-checkout buttons to render alongside the share control.
     *
     * @return ExpressButtonInterface[]
     */
    public function getExpressButtons(): array
    {
        return $this->expressButtonPool->getAvailable();
    }
}
