<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

class Invoiceview extends \MalibuCommerce\MConnect\Controller\Navision
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Invoice\Pdf
     */
    protected $invoicePdf;

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
     * @param \MalibuCommerce\MConnect\Model\Navision\Invoice\Pdf $invoicePdf
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\Response\Http $httpResponse,
        \MalibuCommerce\MConnect\Model\Queue $queue,
        \MalibuCommerce\MConnect\Model\Navision\Invoice\Pdf $invoicePdf,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory
    ) {
        parent::__construct($context, $customerSession, $httpResponse, $queue);
        $this->invoicePdf = $invoicePdf;
        $this->fileFactory = $fileFactory;
    }

    /**
     * Generate and send PDF file wit Invoice
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        try {
            $number = $this->getRequest()->getParam('number');
            $customerNavId = $this->_customerSession->getCustomer()->getNavId();
            $pdf = $this->invoicePdf->get(
                $number,
                $customerNavId
            );
            if ($pdf) {
                $this->fileFactory->create(
                    'invoice_'. $number .'.pdf',
                    $pdf,
                    \Magento\Framework\App\Filesystem\DirectoryList::MEDIA,
                    'application/pdf'
                );
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        return;
    }
}