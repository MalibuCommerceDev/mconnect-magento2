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
     * @param \MalibuCommerce\MConnect\Model\Queue $queue
     * @param \MalibuCommerce\MConnect\Model\Navision\Invoice\Pdf $invoicePdf
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactor
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \MalibuCommerce\MConnect\Model\Queue $queue,
        \MalibuCommerce\MConnect\Model\Navision\Invoice\Pdf $invoicePdf,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactor
    ) {
        parent::__construct($context, $customerSession, $queue);
        $this->invoicePdf = $invoicePdf;
        $this->fileFactory = $fileFactor;
    }

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
                    $number . '.pdf',
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