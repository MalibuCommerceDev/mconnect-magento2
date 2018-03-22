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
     * @var \Magento\Framework\App\Response\Http
     */
    protected $httpResponse;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\App\Response\Http $httpResponse
     * @param \MalibuCommerce\MConnect\Model\Queue $queue
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\Response\Http $httpResponse,
        \MalibuCommerce\MConnect\Model\Queue $queue
    ) {
        parent::__construct($context);
        $this->_customerSession = $customerSession;
        $this->queue = $queue;
        $this->httpResponse = $httpResponse;
    }

    /**
     * Check customer authentication for some actions
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

    /**
     * Return PDF file
     *
     * @param $content
     * @param string $fileName
     */
    public function displayPdf($content, $fileName = 'report.pdf')
    {
        $this->httpResponse->setHeader('Content-Type', 'application/x-pdf', true);
        $this->httpResponse->setHeader('Content-Length', strlen($content), true);
        $this->httpResponse->setHeader('Content-Disposition', "inline; filename= '{$fileName}'");
        echo $content;
    }
}