<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class OrderNavIdEdit extends \Magento\Backend\App\Action implements CsrfAwareActionInterface
{
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    protected function _isAllowed()
    {
        return true;
    }

    public function execute()
    {
        var_dump(__LINE__);exit;
        $post = $this->getRequest()->getPost();
    }
}
