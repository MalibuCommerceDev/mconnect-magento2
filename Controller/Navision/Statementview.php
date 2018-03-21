<?php

namespace MalibuCommerce\MConnect\Controller\Navision;

class Statementview extends \MalibuCommerce\MConnect\Controller\Navision
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Statement\Pdf
     */
    protected $statementPdf;

    /**
     * @var \Magento\Framework\App\Response\Http\FileFactory
     */
    protected $fileFactory;

    /**
     * Statementview constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \MalibuCommerce\MConnect\Model\Queue $queue
     * @param \MalibuCommerce\MConnect\Model\Navision\Statement\Pdf $statementPdf
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactor
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \MalibuCommerce\MConnect\Model\Queue $queue,
        \MalibuCommerce\MConnect\Model\Navision\Statement\Pdf $statementPdf,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactor
    ) {
        parent::__construct($context, $customerSession, $queue);
        $this->statementPdf = $statementPdf;
        $this->fileFactory = $fileFactor;
    }

    public function execute()
    {
        try {
            $customerNavId = $this->_customerSession->getCustomer()->getNavId();
            $startDate     = $this->getRequest()->getParam('date_from');
            $endDate       = $this->getRequest()->getParam('date_to');
            $pdf = $this->statementPdf->get(
                $customerNavId,
                $startDate,
                $endDate
            );
            if ($pdf) {
                $this->fileFactory->create(
                    'statement.pdf',
                    $pdf,
                    \Magento\Framework\App\Filesystem\DirectoryList::MEDIA,
                    'application/pdf'
                );
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        exit;
    }
}