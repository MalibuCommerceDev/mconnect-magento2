<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

class Orderview extends \MalibuCommerce\MConnect\Controller\Navision
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Order\Pdf
     */
    protected $orderPdf;

    /**
     * @var \Magento\Framework\App\Response\Http\FileFactory
     */
    protected $fileFactory;

    /**
     * Orderview constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\App\Response\Http $httpResponse
     * @param \MalibuCommerce\MConnect\Model\Queue $queue
     * @param \MalibuCommerce\MConnect\Model\Navision\Order\Pdf $orderPdf
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\Response\Http $httpResponse,
        \MalibuCommerce\MConnect\Model\Queue $queue,
        \MalibuCommerce\MConnect\Model\Navision\Order\Pdf $orderPdf,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory
    ) {
        parent::__construct($context, $customerSession, $httpResponse, $queue);
        $this->orderPdf = $orderPdf;
        $this->fileFactory = $fileFactory;
    }

    /**
     * Generate and send PDF file wit Order
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        try {
            $number = $this->getRequest()->getParam('number');
            $customerNavId = $this->_customerSession->getCustomer()->getNavId();
            $pdf = $this->orderPdf->get(
                $number,
                $customerNavId
            );
            if ($pdf) {
                $this->displayPdf($pdf, $number . '.pdf');
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        return;
    }
}