<?php
/**
 * @package   Commerce_ShareCart
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\ShareCart\Controller\Cart;

use Commerce\ShareCart\Model\Config;
use Commerce\ShareCart\Model\ShareLinkIssuer;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

/**
 * POST sharecart/cart/generate — issue a share link for the current cart.
 */
class Generate implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly ShareLinkIssuer $shareLinkIssuer,
        private readonly Config $config
    ) {
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->config->isEnabled()) {
            return $result->setHttpResponseCode(404)
                ->setData(['error' => true, 'message' => __('Cart sharing is not available.')]);
        }

        // CSRF: this action mutates state and is reachable by any logged-out
        // visitor, so the form key is mandatory.
        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setHttpResponseCode(403)
                ->setData(['error' => true, 'message' => __('Your session has expired. Please reload the page.')]);
        }

        $shareLink = $this->shareLinkIssuer->issue();

        if (!$shareLink->isSuccess) {
            return $result->setHttpResponseCode(422)
                ->setData(['error' => true, 'message' => $shareLink->message]);
        }

        return $result->setData([
            'error' => false,
            'token' => $shareLink->token,
            'url' => $shareLink->url,
        ]);
    }
}
