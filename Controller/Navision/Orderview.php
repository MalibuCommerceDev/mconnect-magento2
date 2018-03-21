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
     * @param \MalibuCommerce\MConnect\Model\Queue $queue
     * @param \MalibuCommerce\MConnect\Model\Navision\Order\Pdf $orderPdf
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactor
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \MalibuCommerce\MConnect\Model\Queue $queue,
        \MalibuCommerce\MConnect\Model\Navision\Order\Pdf $orderPdf,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactor
    ) {
        parent::__construct($context, $customerSession, $queue);
        $this->orderPdf = $orderPdf;
        $this->fileFactory = $fileFactor;
    }

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