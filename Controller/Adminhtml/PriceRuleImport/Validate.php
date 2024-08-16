<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\PriceRuleImport;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use MalibuCommerce\MConnect\Model\PriceRuleImport\Validator;
use MalibuCommerce\MConnect\Model\PriceRuleImport;
use MalibuCommerce\MConnect\Model\PriceRuleImportFactory;
use Psr\Log\LoggerInterface;

class Validate extends AbstractController
{
    /**
     * @var PriceRuleImportFactory
     */
    private PriceRuleImportFactory $priceRuleImportFactory;

    /**
     * @var Validator
     */
    private Validator $validator;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Context $context
     * @param PriceRuleImportFactory $priceRuleImportFactory
     * @param Validator $validator
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        PriceRuleImportFactory $priceRuleImportFactory,
        Validator $validator,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->priceRuleImportFactory = $priceRuleImportFactory;
        $this->validator = $validator;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = ['error' => false];

        try {
            $websiteId = $this->getRequest()->getPost('website_id');
            $file = $this->getRequest()->getPost('file');

            $importModel = $this->priceRuleImportFactory->create(['data' => [
                PriceRuleImport::WEBSITE_ID => $websiteId,
                PriceRuleImport::FILENAME => $file[0]['file']
            ]]);

            $this->validator->validate($importModel);
        } catch (LocalizedException $e) {
            $result = [
                'error' => true,
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            $this->logger->error($e);
            $result = [
                'error' => true,
                'message' => __('An error occurred while validating the import file. Please check the logs.')
            ];
        }

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($result);
    }
}
