<?php

namespace MalibuCommerce\MConnect\Controller;

use Magento\Framework\App\RequestInterface;

abstract class Navision extends \Magento\Framework\App\Action\Action
{
    /**
     * Customer session
     *
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue
     */
    protected $queue;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \MalibuCommerce\MConnect\Model\Queue $queue
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \MalibuCommerce\MConnect\Model\Queue $queue
    ) {
        parent::__construct($context);
        $this->_customerSession = $customerSession;
        $this->queue = $queue;
    }

    /**
     * Cheorderhistoryck customer authentication for some actions
     *
     * @param RequestInterface $request
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function dispatch(RequestInterface $request)
    {
        if (!$this->_customerSession->authenticate()) {
            $this->_actionFlag->set('', 'no-dispatch', true);
        }
        return parent::dispatch($request);
    }

    public function displayPdf($content, $fileName = 'report.pdf')
    {
        $decoded = base64_decode($content, true);
        if ($decoded === false || strpos($decoded, '%PDF') !== 0) {
            echo $content;
            return;
        }
        $result = $this->resultFactory->create();
        $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_);

        header('Content-Type: application/x-pdf');
        header('Content-Length: ' . strlen($decoded));
        header('Content-Disposition: inline; filename="' . $fileName . '"');
        echo $decoded;
    }
}