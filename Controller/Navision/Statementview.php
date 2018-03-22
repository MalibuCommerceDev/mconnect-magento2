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
     * @param \Magento\Framework\App\Response\Http $httpResponse
     * @param \MalibuCommerce\MConnect\Model\Queue $queue
     * @param \MalibuCommerce\MConnect\Model\Navision\Statement\Pdf $statementPdf
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\Response\Http $httpResponse,
        \MalibuCommerce\MConnect\Model\Queue $queue,
        \MalibuCommerce\MConnect\Model\Navision\Statement\Pdf $statementPdf,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory
    ) {
        parent::__construct($context, $customerSession, $httpResponse, $queue);
        $this->statementPdf = $statementPdf;
        $this->fileFactory = $fileFactory;
    }

    /**
     * Generate and send PDF file wit Statement
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
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
                    'statement_'.md5($customerNavId).'.pdf',
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