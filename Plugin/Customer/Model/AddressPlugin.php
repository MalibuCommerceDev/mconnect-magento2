<?php

namespace MalibuCommerce\MConnect\Plugin\Customer\Model;

use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Model\Address as Subject;
use Magento\Customer\Model\AddressFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Psr\Log\LoggerInterface;

class AddressPlugin
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /** @var AddressFactory */
    protected $addressModel;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * AddressPlugin constructor.
     *
     * @param \MalibuCommerce\MConnect\Model\Config $config
     * @param AddressFactory                        $addressModel
     * @param LoggerInterface                       $logger
     */
    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $config,
        AddressFactory $addressModel,
        LoggerInterface $logger,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->config = $config;
        $this->addressModel = $addressModel;
        $this->logger = $logger;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Create New address after magento address saved as both default billing and default shipping
     *
     * @param \Magento\Customer\Model\Address $subject
     * @param \Magento\Customer\Model\Address $savedAddress
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @return \Magento\Customer\Model\Address
     */
    public function afterSave(
        \Magento\Customer\Model\Address $subject,
        $savedAddress
    ) {
        if (!$this->config->get('customer/split_default_customer_address_into_two')) {

            return $savedAddress;
        }

        $customer = $this->customerRepository->getById($savedAddress->getCustomer()->getId());

        if ($customer->getDefaultBilling() == $customer->getDefaultShipping()
            && $savedAddress->getId() == $customer->getDefaultBilling()
        ) {
            try {
                if ($savedAddress->getIsDuplicate()) {
                    return $this;
                }
                $oriAddress = $this->addressModel->create()->load($savedAddress->getId());
                $newAddress = clone $oriAddress;
                $newAddress
                    ->setIsDefaultBilling(false)
                    ->setIsDefaultShipping(true)
                    ->setIsDuplicate(true)
                    ->setSkipMconnect(true)
                    ->save();
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }

        return $savedAddress;
    }

    /**
     * @param Subject          $subject
     * @param                  $resultAddressModel
     * @param AddressInterface $address
     *
     * @return mixed
     */
    public function afterUpdateData(Subject $subject, $resultAddressModel, AddressInterface $address)
    {
        if (!$address->getCustomAttribute('nav_id')) {

            return $resultAddressModel;
        }
        $specialNavId = explode('|', (string)$address->getCustomAttribute('nav_id')->getValue());
        $specialMarker = \MalibuCommerce\MConnect\Model\Queue\Customer::CUSTOMER_ADDRESS_SPECIAL_MARKER;
        $isAddressSavedByMconnectLogic = isset($specialNavId[0]) && $specialNavId[0] == $specialMarker;
        if ($isAddressSavedByMconnectLogic) {
            $ignoreValidation = (bool)$this->config->getWebsiteData(
                'customer/ignore_customer_address_validation',
                $specialNavId[1] ?? null
            );
            $normilizedNavId = $specialNavId[2] ?? 'DEFAULT';
            $resultAddressModel
                ->setNavId($normilizedNavId)
                ->setCustomAttribute('nav_id', $normilizedNavId)
                ->setShouldIgnoreValidation($ignoreValidation)
                ->setSkipMconnect(true);
        }

        return $resultAddressModel;
    }
}
