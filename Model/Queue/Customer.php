<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;

class Customer extends \MalibuCommerce\MConnect\Model\Queue
{
    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var \Magento\Customer\Model\AddressFactory
     */
    protected $addressFactory;

    /**
     * @var \Magento\Directory\Model\RegionFactory
     */
    protected $regionFactory;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Customer
     */
    protected $navCustomer;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \MalibuCommerce\MConnect\Helper\Data
     */
    protected $helper;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\FlagFactory
     */
    protected $queueFlagFactory;

    /** @var \Magento\Customer\Model\CustomerFactory */
    protected $customer;

    /**
     * @var \Magento\Customer\Model\AddressFactory
     */
    protected $address;

    /**
     * @var \Magento\Customer\Model\ResourceModel\Address\CollectionFactory
     */
    protected $addressCollectionFactory;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Magento\Eav\Model\AttributeRepository
     */
    protected $attributeRepository;

    /**
     * @var array
     */
    protected $customAttributesMap = [];

    public function __construct(
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \MalibuCommerce\MConnect\Model\Navision\Customer $navCustomer,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Helper\Data $helper,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Customer\Model\ResourceModel\Address\CollectionFactory $addressCollectionFactory,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Eav\Model\AttributeRepository $attributeRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->regionFactory = $regionFactory;
        $this->navCustomer = $navCustomer;
        $this->config = $config;
        $this->helper = $helper;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->customerFactory = $customerFactory;
        $this->addressFactory = $addressFactory;
        $this->addressCollectionFactory = $addressCollectionFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->attributeRepository = $attributeRepository;
    }

    public function initImport()
    {
        return $this;
    }

    public function mapEavToNavCustomCustomerAttribute($eavAttributeCode, $navAttributeCode)
    {
        $this->customAttributesMap[$eavAttributeCode] = $navAttributeCode;

        return $this->customAttributesMap;
    }

    public function exportAction($entityId = null)
    {
        try {
            $customerEntity = $this->customerRepository->getById($entityId);
            $customerDataModel = $this->customerFactory->create()->load($entityId);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('Customer ID "%1" does not exist', $entityId));
        } catch (\Exception $e) {
            throw new LocalizedException(__('Customer ID "' . $entityId . '" loading error: %s', $e->getMessage()));
        }

        $response = $this->navCustomer->import($customerEntity, $customerDataModel);
        $status = (string)$response->result->status;
        if ($status === 'Processed') {
            $navId = (string)$response->result->Customer->nav_record_id;
            if ($customerDataModel->getNavId() != $navId) {
                $customerDataModel->setNavId($navId)
                    ->setSkipMconnect(true)
                    ->save();
            }
            $this->messages .= sprintf('Customer exported, NAV ID: %s', $navId);

            return true;
        }

        if ($status == 'Error') {
            $errors = array();
            foreach ($response->result->Customer as $customer) {
                foreach ($customer->error as $error) {
                    $errors[] = (string)$error->message;
                }
            }
            if (empty($errors)) {
                $errors[] = 'Unknown API error.';
            }
            $this->messages .= implode("\n", $errors);

            throw new LocalizedException(implode("\n", $errors));
        }

        throw new LocalizedException(__('Unexpected status: "%1". Check log for details.', $status));
    }

    public function importAction()
    {
        $this->initImport();

        $count = 0;
        $page = 0;
        $lastSync = false;
        $lastUpdated = $this->getLastSyncTime(Flag::FLAG_CODE_LAST_CUSTOMER_SYNC_TIME);
        do {
            $result = $this->navCustomer->export($page++, $lastUpdated);
            foreach ($result->customer as $data) {
                $importResult = $this->importCustomer($data);
                if ($importResult) {
                    $count++;
                }
                $this->messages .= PHP_EOL;
            }
            if (!$lastSync) {
                $lastSync = $result->status->current_date_time;
            }
        } while (isset($result->status->end_of_records) && (string)$result->status->end_of_records === 'false');
        if ($count > 0) {
            $this->setLastSyncTime(Flag::FLAG_CODE_LAST_CUSTOMER_SYNC_TIME, $lastSync);
            $this->messages .= PHP_EOL . 'Successfully processed ' . $count . ' NAV records(s).';
        } else {
            $this->messages .= PHP_EOL . 'Nothing to import.';
        }
    }

    protected function importCustomer($data)
    {
        $email = (string)$data->cust_email;
        if (empty($email)) {
            $this->messages .= 'Skipping NAV ID "' . $data->cust_nav_id . '": email is empty' . PHP_EOL;

            return false;
        }

        /**
         * Persist customer entity
         */
        $websiteId = $this->config->get('customer/default_website');
        $customerExists = false;
        try {
            $customer = $this->customerFactory->create()->setWebsiteId($websiteId)->loadByEmail($email);
            if ($customer->getId()) {
                $customerExists = true;
            }
        } catch (\Exception $e) {
            $this->messages .= $email . ': ' . $e->getMessage();

            return false;
        }

        if (!$customerExists) {
            $taxable = (string)$data->cust_taxable;
            $customer->setEmail($email)
                ->setGroupId(
                    empty($taxable) || $taxable == 'false'
                        ? $this->config->get('customer/default_group_nontaxable')
                        : $this->config->get('customer/default_group_taxable')
                )
                ->setWebsiteId($websiteId);
        }

        $firstname = (string)$data->cust_first_name;
        $lastname = (string)$data->cust_last_name;
        if (empty($lastname)) {
            $parts = explode(' ', $firstname);
            if (count($parts) > 1) {
                $firstname = $parts[0];
                $lastname = $parts[1];
            } else {
                $lastname = 'Co.';
            }
        }

        $customer->setFirstname($firstname)
            ->setLastname($lastname)
            ->setSkipMconnect(true)
            ->setNavId((string)$data->cust_nav_id)
            ->setNavPaymentTerms((string)$data->cust_payment_terms)
            ->setNavPriceGroup((string)$data->cust_price_group);

        /**
         * Set required user defined attributes
         */
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('is_required', '1')
            ->addFilter('is_user_defined', '1')
            ->create();

        $attributes = $this->attributeRepository->getList(
            'customer',
            $searchCriteria
        )->getItems();

        foreach ($attributes as $attribute) {
            switch ($attribute->getBackendType()) {
                case 'decimal':
                    $value = 0.00;
                    break;
                case 'int':
                    $value = 0;
                    break;
                case 'datetime':
                    $value = '0000-00-00 00:00:00';
                    break;
                default:
                    $value = 'N/A';
                    break;
            }
            $customer->setDataUsingMethod($attribute->getAttributeCode(), $value);
        }

        try {
            if ($customer->hasDataChanges() || !empty($this->customAttributesMap)) {
                $customer->save();

                $this->saveCustomCustomerAttributes($customer, $data);

                if ($customerExists) {
                    $this->messages .= $email . ': updated';
                } else {
                    $this->messages .= $email . ': created';

                    if ($this->helper->sendNewCustomerEmail($customer)) {
                        $this->messages .= $email . ': new customer email sent';
                    }
                }
            } else {
                $this->messages .= $email . ': skipped';
            }
        } catch (\Exception $e) {
            $this->messages .= $email . ': ' . $e->getMessage();
        }

        /**
         * Add addresses
         */
        if (!empty($data->address)) {
            foreach ($data->address as $addressData) {
                $this->importAddress($customer, $addressData);
            }
        }

        return true;
    }

    public function saveCustomCustomerAttributes(
        \Magento\Customer\Model\Customer $customerDataModel,
        \simpleXMLElement $data
    ) {
        foreach ($this->customAttributesMap as $eavAttributeCode => $navAttributeCode) {
            if (!isset($data->$navAttributeCode)) {
                continue;
            }

            $value = (string) $data->$navAttributeCode;
            $attribute = $customerDataModel->getResource()->getAttribute($eavAttributeCode);
            if ($attribute->usesSource()) {
                $value = $attribute->getSource()->getOptionId($value);
            }

            $customerData = $customerDataModel->getDataModel();
            $customerData->setId($customerDataModel->getData('entity_id'));
            $customerData->setCustomAttribute($eavAttributeCode, $value);
            $customerDataModel->updateData($customerData);
            $customerDataModel->getResource()->saveAttribute($customerDataModel, $eavAttributeCode);
        }
    }

    protected function importAddress($customer, $addressData)
    {
        static $email = null, $country = null, $state = null;
        $address = false;

        if (!empty($addressData->addr_nav_id)) {
            $collection = $this->addressCollectionFactory->create();
            $collection->addFieldToFilter('parent_id', $customer->getId())
                ->addFieldToFilter('nav_id', (string)$addressData->addr_nav_id);

            $addresses = $collection->getItems();
            if ($addresses) {
                $address = reset($addresses);
            }
        }

        $addressExists = true;
        if (!$address || !$address->getId()) {
            $address = $this->addressFactory->create();
            $addressExists = false;
        }

        if (empty($email) || $email != $customer->getEmail()) {
            $country = (string)$addressData->addr_country;
            $state = (string)$addressData->addr_state;
            $email = $customer->getEmail();
        }
        $country = !empty((string)$addressData->addr_country) ? (string)$addressData->addr_country : $country;
        $state = !empty((string)$addressData->addr_state) ? (string)$addressData->addr_state : $state;
        $region = $this->getRegion($country, $state);

        $firstname = (string)$addressData->addr_name;
        $lastname = (string)$addressData->addr_name2;
        if (empty($lastname)) {
            $parts = explode(' ', $firstname);
            if (count($parts) > 1) {
                $firstname = $parts[0];
                unset($parts[0]);
                $lastname = implode(' ', $parts);
            } else {
                $lastname = 'Co.';
            }
        }
        $telephone = (string)$addressData->addr_phone;
        $telephone = empty($telephone) ? 'N/A' : $telephone;

        $address
            ->setParentId($customer->getId())
            ->setFirstname($firstname)
            ->setLastname($lastname)
            ->setStreet([(string)$addressData->addr_street])
            ->setCity((string)$addressData->addr_city)
            ->setCountryId($country)
            ->setRegionId($region)
            ->setPostcode((string)$addressData->addr_post_code)
            ->setTelephone($telephone)
            ->setFax((string)$addressData->addr_fax)
            ->setNavId((string)$addressData->addr_nav_id)
            ->setSkipMconnect(true)
            ->setShouldIgnoreValidation(true);

        if ((string)$addressData->addr_nav_id == 'DEFAULT') {
            $address->setIsDefaultBilling(true);
            $address->setIsDefaultShipping(true);
        }

        try {
            if ($address->hasDataChanges()) {
                $address->save();
                if ($addressExists) {
                    $this->messages .= '; Address ' . $addressData->addr_nav_id . ': updated' . PHP_EOL;
                } else {
                    $this->messages .= '; Address ' . $addressData->addr_nav_id . ': created' . PHP_EOL;
                }
            } else {
                $this->messages .= '; Address ' . $addressData->addr_nav_id . ': skipped' . PHP_EOL;
            }
        } catch (\Exception $e) {
            $this->messages .= '; Address ' . $addressData->addr_nav_id . ': ' . $e->getMessage() . PHP_EOL;
        }
    }

    protected function getRegion($country, $state)
    {
        $region = $this->regionFactory->create()->loadByCode($state, $country);
        if (!$region || !$region->getId()) {
            return $state;
        }

        return $region->getRegionId();
    }
}
