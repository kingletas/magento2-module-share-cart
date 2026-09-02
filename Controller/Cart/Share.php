<?php
/**
 * @package   Commerce_ShareCart
 * @copyright Copyright (c) the Commerce modules authors
 * @license   OSL-3.0 https://opensource.org/licenses/OSL-3.0
 */

declare(strict_types=1);

namespace Commerce\ShareCart\Controller\Cart;

use Commerce\ShareCart\Model\Cart\RestoreResult;
use Commerce\ShareCart\Model\Cart\SharedCartRestorer;
use Commerce\ShareCart\Model\Config;
use Commerce\ShareCart\Model\Validator\TokenFormatValidator;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;

/**
 * GET sharecart/cart/share/token/{token} — open a shared cart.
 */
class Share implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly MessageManagerInterface $messageManager,
        private readonly SharedCartRestorer $restorer,
        private readonly TokenFormatValidator $tokenValidator,
        private readonly Config $config
    ) {
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();

        if (!$this->config->isEnabled()) {
            return $redirect->setPath('checkout/cart');
        }

        $token = (string) $this->request->getParam('token', '');

        // Reject malformed tokens before touching the database: it is a free
        // filter against enumeration traffic.
        if (!$this->tokenValidator->isValid($token)) {
            $this->messageManager->addErrorMessage(__('That shared cart link is not valid.'));

            return $redirect->setPath('checkout/cart');
        }

        $result = $this->restorer->restore($token);

        $this->report($result);

        return $redirect->setPath('checkout/cart');
    }

    private function report(RestoreResult $result): void
    {
        if ($result->isSuccess()) {
            $this->messageManager->addSuccessMessage(__('The shared cart has been added to your cart.'));

            return;
        }

        // A failed outcome always carries a message; a new one that forgot would
        // otherwise show an empty error.
        $this->messageManager->addErrorMessage(
            $result->message ?? __('We could not open that shared cart. Please try again.')
        );
    }
}
