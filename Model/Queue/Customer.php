<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Model\AddressRegistry;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;

class Customer extends \MalibuCommerce\MConnect\Model\Queue implements ImportableEntity
{
    const CODE                   = 'customer';
    const NAV_XML_NODE_ITEM_NAME = 'customer';

    const CUSTOMER_ADDRESS_SPECIAL_MARKER = 'MCONNECT';

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /** @var AddressRepositoryInterface */
    protected $addressRepository;

    /** @var AddressInterfaceFactory */
    protected $addressFactory;

    /** @var AddressRegistry */
    protected $addressRegistry;

    /** @var RegionInterfaceFactory */
    protected $addressRegionFactory;

    /** @var RegionFactory */
    protected $directoryRegionFactory;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Customer
     */
    protected $navCustomer;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \MalibuCommerce\MConnect\Model\QueueFactory
     */
    protected $queueFactory;

    /**
     * @var \MalibuCommerce\MConnect\Helper\Mail
     */
    protected $mailer;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\FlagFactory
     */
    protected $queueFlagFactory;

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
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        AddressRepositoryInterface $addressRepository,
        AddressInterfaceFactory $addressFactory,
        AddressRegistry $addressRegistry,
        RegionInterfaceFactory $addressRegionFactory,
        RegionFactory $directoryRegionFactory,
        \MalibuCommerce\MConnect\Model\Navision\Customer $navCustomer,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Helper\Mail $mailer,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Eav\Model\AttributeRepository $attributeRepository,
        \MalibuCommerce\MConnect\Model\QueueFactory $queueFactory
    ) {
        $this->customerRepository = $customerRepository;
        $this->customerFactory = $customerFactory;
        $this->addressRepository = $addressRepository;
        $this->addressFactory = $addressFactory;
        $this->addressRegistry = $addressRegistry;
        $this->addressRegionFactory = $addressRegionFactory;
        $this->directoryRegionFactory = $directoryRegionFactory;
        $this->navCustomer = $navCustomer;
        $this->config = $config;
        $this->mailer = $mailer;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->attributeRepository = $attributeRepository;
        $this->queueFactory = $queueFactory;
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

    /**
     * @param $entityId
     *
     * @return bool
     * @throws LocalizedException
     */
    public function exportAction($entityId)
    {
        try {
            $customerEntity = $this->customerRepository->getById($entityId);
            $customerDataModel = $this->customerFactory->create()->load($entityId);
            $customerMageCustomAttributes = $customerEntity->getCustomAttributes();
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('Customer ID "%1" does not exist', $entityId));
        } catch (\Throwable $e) {
            throw new LocalizedException(__('Customer ID "' . $entityId . '" loading error: %s', $e->getMessage()));
        }

        $websiteId = $customerEntity->getWebsiteId();
        $response = $this->navCustomer->import($customerEntity, $customerDataModel, $websiteId);
        $status = (string)$response->result->status;
        if ($status === 'Processed') {
            $navId = (string)$response->result->Customer->nav_record_id;
            if ($customerDataModel->getNavId() != $navId) {
                $customerDataModel->setNavId($navId)
                    ->setSkipMconnect(true)
                    ->save();

                if (!empty($customerMageCustomAttributes)) {
                    $customerData = $customerDataModel->getDataModel();
                    foreach ($customerMageCustomAttributes as $attribute) {
                        if ($attribute->getAttributeCode() == 'nav_id') {
                            continue;
                        }
                        $customerData->setCustomAttribute($attribute->getAttributeCode(), $attribute->getValue());
                    }
                    $customerDataModel->updateData($customerData)->save();
                }
            }
            $this->messages .= sprintf('Customer exported, NAV ID: %s', $navId);

            return true;
        }

        if ($status == 'Error') {
            $errors = [];
            foreach ($response->result->Customer as $customer) {
                foreach ($customer->error as $error) {
                    $errors[] = (string)$error->message;
                }
            }
            if (empty($errors)) {
                $errors[] = 'Unknown API error.';
            }
            $this->messages .= implode("\n", $errors);

            throw new \Exception(implode("\n", $errors));
        }

        throw new LocalizedException(__('Unexpected status: "%1". Check log for details.', $status));
    }

    /**
     * @param     $websiteId
     * @param int $navPageNumber
     *
     * @return bool|\Magento\Framework\DataObject|Customer
     * @throws \Exception
     */
    public function importAction($websiteId, $navPageNumber = 0)
    {
        $this->initImport();

        return $this->processMagentoImport($this->navCustomer, $this, $websiteId, $navPageNumber);
    }

    /**
     * Backward compatibility method
     *
     * @param \SimpleXMLElement $data
     * @param int               $websiteId
     */
    public function importCustomer($data, $websiteId = 0)
    {
        $this->importEntity($data, $websiteId);
    }

    /**
     * @param \SimpleXMLElement $data
     * @param int               $websiteId
     *
     * @return bool
     * @throws \Magento\Framework\Exception\InputException
     */
    public function importEntity(\SimpleXMLElement $data, $websiteId)
    {
        $importByNavId = (bool)$this->config->get('customer/import_by_nav_id');

        /**
         * Persist customer entity
         */
        $websiteId = $websiteId ? : $this->config->get('customer/default_website');
        $identity = $importByNavId ? (string)$data->cust_nav_id : (string)$data->cust_email;
        if (empty($identity)) {
            $this->messages .= 'Customer "' . $identity . '": SKIPPED - email/NAV ID is empty' . PHP_EOL;

            return false;
        }

        if (!$importByNavId) {
            try {
                /** @var \Magento\Customer\Model\Customer $customer */
                $customer = $this->customerFactory->create()->setWebsiteId($websiteId);
                $customer = $customer->loadByEmail($identity);
                $this->saveCustomerData($customer, $data, $websiteId);
            } catch (\Throwable $e) {
                $this->messages .= 'Customer "' . $identity . '": ERROR - ' . $e->getMessage() . PHP_EOL;

                return false;
            }
        } else {
            $searchCriteria = $this->searchCriteriaBuilder->addFilter('nav_id', $identity)->create();
            $searchResult = $this->customerRepository->getList($searchCriteria);

            if (!$searchResult->getItems()) {
                try {
                    /** @var \Magento\Customer\Model\Customer $customer */
                    $customer = $this->customerFactory->create()->setWebsiteId($websiteId);
                    $customer = $customer->loadByEmail($identity);
                    $this->saveCustomerData($customer, $data, $websiteId);
                } catch (\Throwable $e) {
                    $this->messages .= 'Customer "' . $identity . '": ERROR - ' . $e->getMessage() . PHP_EOL;

                    return false;
                }

                return true;
            }

            // Update all customers with same NAV ID
            foreach ($searchResult as $customerEntity) {
                try {
                    /** @var \Magento\Customer\Model\Customer $customer */
                    $customer = $this->customerFactory->create()->setWebsiteId($websiteId);
                    $customer = $customer->load($customerEntity->getId());
                    $this->saveCustomerData($customer, $data, $websiteId);
                } catch (\Throwable $e) {
                    $this->messages .= 'Customer "' . $identity . '": ERROR - ' . $e->getMessage() . PHP_EOL;
                }
            }
        }

        return true;
    }

    /**
     * @param \Magento\Customer\Model\Customer $customer
     * @param \SimpleXMLElement                $data
     * @param int                              $websiteId
     *
     * @return bool
     * @throws InputException
     */
    protected function saveCustomerData(\Magento\Customer\Model\Customer $customer, \SimpleXMLElement $data, $websiteId)
    {
        $email = (string)$data->cust_email;
        $customerExists = $customer->getId();
        if (!$customerExists) {
            $customer->setEmail($email)->setWebsiteId($websiteId);
        }
        $taxable = (string)$data->cust_taxable;
        $taxable = !empty($taxable) && $taxable != 'false';
        if ($customer->getNavTaxable() === null || $taxable != (bool)$customer->getNavTaxable()) {
            $customer->setGroupId(
                $taxable
                    ? $this->config->get('customer/default_group_taxable')
                    : $this->config->get('customer/default_group_nontaxable')
            );
        }
        $customer->setNavTaxable((int)$taxable);

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
            ->setNavCurrencyCode((string)$data->currency_code)
            ->setNavPriceGroup((string)$data->cust_price_group);

        $password = (string)$data->cust_pswd;
        if (!empty($password)) {
            $customer->setPassword(base64_decode($password));
        }

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

        if ($customerExists) {
            $id = 'Customer: "' . (string)$data->cust_nav_id . '/' . $customer->getId() . '"';
        } else {
            $id = 'Customer: "' . (string)$data->cust_nav_id . '/' . $email . '"';
        }
        try {
            if ($customer->hasDataChanges() || !empty($this->customAttributesMap)) {
                foreach ($customer->getAddresses() as $address) {
                    $address->setShouldIgnoreValidation(true);
                }
                $customer->save();
                $this->saveCustomCustomerAttributes($customer, $data);

                if ($customerExists) {
                    $this->messages .= $id . ': UPDATED' . PHP_EOL;
                } else {
                    $this->messages .= $id . ': CREATED' . PHP_EOL;

                    if ($this->mailer->resetPasswordForNewCustomer($customer)) {
                        $this->messages .= $id . ': REST PSWD EMAIL SENT';
                    }
                }
            } else {
                $this->messages .= $id . ': SKIPPED' . PHP_EOL;
            }
        } catch (\Throwable $e) {
            $this->messages .= $id . ': ERROR - ' . $e->getMessage() . PHP_EOL;
        }

        /**
         * Add addresses
         */
        if (!empty($data->address)) {
            $company = (string)$data->cust_company_name;
            foreach ($data->address as $address) {
                $address->cust_company_name = $company;
            }
            $this->importCustomerAddresses($customer, $data->address, $websiteId);
        }
        $this->messages .= PHP_EOL . PHP_EOL;


        return true;
    }

    public function saveCustomCustomerAttributes(
        \Magento\Customer\Model\Customer $customerDataModel,
        \simpleXMLElement $data
    ) {
        foreach ($this->customAttributesMap as $eavAttributeCode => $navAttributeCode) {
            if (is_array($navAttributeCode)) {
                $canFormTheValue = false;
                $values = [];
                foreach ($navAttributeCode as $navCode) {
                    if (!isset($data->$navCode)) {
                        continue;
                    }

                    $values[] = $this->getCustomerEavAttributeValue(
                        $customerDataModel,
                        $data,
                        $navCode,
                        $eavAttributeCode
                    );

                    $canFormTheValue = true;
                }
                if (!$canFormTheValue) {
                    continue;
                }
                $value = implode('', $values);
            } else {
                if (!isset($data->$navAttributeCode)) {
                    continue;
                }

                $value = $this->getCustomerEavAttributeValue(
                    $customerDataModel,
                    $data,
                    $navAttributeCode,
                    $eavAttributeCode
                );
            }

            $customerData = $customerDataModel->getDataModel();
            $customerData->setId($customerDataModel->getData('entity_id'));
            $customerData->setCustomAttribute($eavAttributeCode, $value);
            $customerDataModel->updateData($customerData);
            $customerDataModel->getResource()->saveAttribute($customerDataModel, $eavAttributeCode);
        }
    }

    /**
     * @param \Magento\Customer\Model\Customer $customerDataModel
     * @param \simpleXMLElement                $data
     * @param                                  $navAttributeCode
     * @param                                  $eavAttributeCode
     *
     * @return int|string
     */
    public function getCustomerEavAttributeValue(
        \Magento\Customer\Model\Customer $customerDataModel,
        \simpleXMLElement $data,
        $navAttributeCode,
        $eavAttributeCode
    ) {

        $value = (string)$data->$navAttributeCode;
        $possibleBoolValue = strtolower($value);
        if ($possibleBoolValue == 'true' || $possibleBoolValue == 'false') {
            $value = $possibleBoolValue == 'true' ? 1 : 0;
        } else {
            $attribute = $customerDataModel->getResource()->getAttribute($eavAttributeCode);
            if ($attribute->usesSource()) {
                $value = $attribute->getSource()->getOptionId($value);
            }
        }

        return $value;
    }

    /**
     * @param \Magento\Customer\Model\Customer $customer
     * @param \SimpleXMLElement                $addresses
     * @param int|string|null                  $websiteId
     *
     * @return array
     */
    public function importCustomerAddresses($customer, $addresses, $websiteId): array
    {
        $importedNewAddresses = $importedExistingAddresses = [];
        $customerDefaultBillingAddress = $customer->getDefaultBillingAddress();
        $customerDefaultShippingAddress = $customer->getDefaultShippingAddress();

        foreach ($addresses as $navAddressData) {
            // -- Search by Magento Address ID --
            $addressId = filter_var($navAddressData->addr_mag_id, FILTER_VALIDATE_INT);
            if ($addressId) {
                $updatedAddress = $this->updateExistingAddress($addressId, $navAddressData, $websiteId);
                if ($updatedAddress) {
                    $importedExistingAddresses[$updatedAddress->getId()] = $navAddressData;
                }
                continue;
            }

            // -- Search by Magento default Address --
            $updatingDefaultAddressesMode = false;
            $isNavDefaultBilling = $this->isAddressDefaultBilling($navAddressData);
            $isNavDefaultShipping = $this->isAddressDefaultShipping($navAddressData);

            // If nav address is default billing then attempt to update default existing billing address
            if ($customerDefaultBillingAddress && $isNavDefaultBilling) {
                $addressId = $customerDefaultBillingAddress->getId();
                $updatingDefaultAddressesMode = true;
                // Split update mode
                if ($this->config->getWebsiteData('customer/split_nav_customer_address', $websiteId)
                    && $isNavDefaultBilling && $isNavDefaultShipping
                ) {
                    $navAddressData->is_default_shipping = 'false';
                }

                $updatedAddress = $this->updateExistingAddress($addressId, $navAddressData, $websiteId);
                if ($updatedAddress) {
                    $importedExistingAddresses[$updatedAddress->getId()] = $navAddressData;
                }
            }
            // If nav address is default shipping then attempt to update default existing shipping address
            if ($customerDefaultShippingAddress && $isNavDefaultShipping) {
                $addressId = $customerDefaultShippingAddress->getId();
                $updatingDefaultAddressesMode = true;
                if ($this->config->getWebsiteData('customer/split_nav_customer_address', $websiteId)
                    && $isNavDefaultBilling && $isNavDefaultShipping
                ) {
                    $navAddressData->is_default_billing = 'false';
                }

                $updatedAddress = $this->updateExistingAddress($addressId, $navAddressData, $websiteId);
                if ($updatedAddress) {
                    $importedExistingAddresses[$updatedAddress->getId()] = $navAddressData;
                }
            }

            if ($updatingDefaultAddressesMode) {
                continue;
            }

            // -- Search by Street and Postcode --
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('street', '%' . ((string)$navAddressData->addr_street) . '%', 'like')
                ->addFilter('postcode', (string)$navAddressData->addr_post_code)
                ->addFilter('parent_id', (int)$customer->getId())
                ->create();
            $searchResult = $this->addressRepository->getList($searchCriteria)->getItems();
            /** @var \Magento\Customer\Api\Data\AddressInterface $addressByStreet */
            $address = current($searchResult);
            if ($address) {
                // If address exists but its default flag not same as for nav address - create new one -
                // but only when default flag is actually set for nav address and it is true
                // and only when shipping|billing address doesn't exist for a customer yet
                if (($address->isDefaultShipping() && $this->isAddressDefaultBilling($navAddressData)
                     && !$customerDefaultBillingAddress
                    )
                    || ($address->isDefaultBilling() && $this->isAddressDefaultShipping($navAddressData)
                        && !$customerDefaultShippingAddress
                    )
                ) {
                    $createdAddress = $this->createAddress($customer, $navAddressData, $websiteId);
                    $importedNewAddresses[] = $createdAddress;
                } else {
                    $updatedAddress = $this->updateExistingAddress($address->getId(), $navAddressData, $websiteId);
                    if ($updatedAddress) {
                        $importedExistingAddresses[$updatedAddress->getId()] = $navAddressData;
                    }
                }
                // Prevent address duplicate creation
                continue;
            }

            // -- Add new address --
            $createdAddress = $this->createAddress($customer, $navAddressData, $websiteId);
            $importedNewAddresses[] = $createdAddress;
        }

        return [$importedExistingAddresses, $importedNewAddresses];
    }

    /**
     * @param int               $addressId
     * @param \SimpleXMLElement $navAddressData
     * @param int               $websiteId
     *
     * @return \Magento\Customer\Api\Data\AddressInterface|null
     */
    protected function updateExistingAddress($addressId, $navAddressData, $websiteId)
    {
        try {
            try {
                /** @var \Magento\Customer\Api\Data\AddressInterface $address */
                $address = $this->addressRepository->getById($addressId);
            } catch (\NoSuchEntityException $e) {
                $this->messages .= sprintf(
                    '--> Address "%s": IGNORED - not found by Magento ID' . PHP_EOL,
                    (string)$navAddressData->addr_street,
                );

                return null;
            }

            if ($address->isDefaultShipping()
                && !$this->config->getWebsiteData('customer/update_customer_shipping_address', $websiteId)
            ) {
                $this->messages .= sprintf(
                    '--> Address "%s": UPDATE IGNORED - address marked as default shipping, but shipping address update is disabled' . PHP_EOL,
                    (string)$navAddressData->addr_street,
                );

                return null;
            }

            $region = $address->getRegion();
            $country = (string)$navAddressData->addr_country;
            $state = (string)$navAddressData->addr_state;
            $searchRegion = $this->directoryRegionFactory->create()->loadByCode($state, $country);
            if ($searchRegion && $searchRegion->getId()) {
                /** @var \Magento\Customer\Api\Data\RegionInterface $region */
                $region = $this->addressRegionFactory->create();
                $region->setRegionCode($searchRegion->getCode())
                    ->setRegion($searchRegion->getName())
                    ->setRegionId($searchRegion->getRegionId());
            }

            $firstname = (string)$navAddressData->addr_name;
            $lastname = (string)$navAddressData->addr_name2;
            if (empty($firstname) || empty($lastname)) {
                $firstname = (string)$navAddressData->addr_first_name;
                $lastname = (string)$navAddressData->addr_last_name;
            }
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

            if (!isset($navAddressData->addr_company_name)) {
                $company = (string)$navAddressData->cust_company_name;
            } else {
                $company = (string)$navAddressData->addr_company_name;
            }
            if ($firstname == $company) {
                $company = '';
            }
            $telephone = (string)$navAddressData->addr_phone;
            $telephone = empty($telephone) ? 'N/A' : $telephone;
            $streetData = [];
            if (!empty($navAddressData->addr_street)) {
                $streetData[] = (string)$navAddressData->addr_street;
            }
            if (!empty($navAddressData->address_2)) {
                $streetData[] = (string)$navAddressData->address_2;
            }

            $address
                ->setCountryId($country)
                ->setPostcode((string)$navAddressData->addr_post_code)
                ->setRegion($region)
                ->setCompany($company)
                ->setStreet($streetData)
                ->setTelephone($telephone)
                ->setCity((string)$navAddressData->addr_city)
                ->setFirstname($firstname)
                ->setLastname($lastname)
                ->setIsDefaultBilling($this->isAddressDefaultBilling($navAddressData))
                ->setIsDefaultShipping($this->isAddressDefaultShipping($navAddressData))
                ->setFax((string)$navAddressData->addr_fax)
                ->setRegionId($region->getRegionId())
                ->setCustomAttribute(
                    'nav_id',
                    $this->getSpeciallyMarkedAddressNavId($websiteId, (string)$navAddressData->addr_nav_id)
                );

            $result = $this->addressRepository->save($address);
            // Needed so that importCustomerAddresses() loop keep getting reloaded address objects from database with
            // latest customer data (otherwise for new customer default billing|shipping address IDs
            // will not be updated when loading address
            $this->addressRegistry->remove($address->getId());
            $this->messages .= sprintf('--> Address "%s": UPDATED' . PHP_EOL, (string)$navAddressData->addr_street);

            return $result;
        } catch (\Throwable $e) {
            $this->messages .= sprintf(
                '--> Address "%s": ERROR - %s' . PHP_EOL,
                (string)$navAddressData->addr_street,
                $e->getMessage()
            );
        }

        return null;
    }

    /**
     * @param \Magento\Customer\Model\Customer $customer
     * @param \SimpleXMLElement                $navAddressData
     * @param int                              $websiteId
     *
     * @return \Magento\Customer\Api\Data\AddressInterface|null
     */
    protected function createAddress($customer, $navAddressData, $websiteId)
    {
        try {
            if ($this->isAddressDefaultShipping($navAddressData)
                && !$this->config->getWebsiteData('customer/update_customer_shipping_address', $websiteId)
                && $customer->getDefaultShippingAddress() && $customer->getDefaultShippingAddress()->getId()
            ) {
                $this->messages .= sprintf(
                    '--> Address "%s": CREATE IGNORED - address marked as default shipping, but shipping address update is disabled' . PHP_EOL,
                    (string)$navAddressData->addr_street,
                );

                return null;
            }

            /** @var \Magento\Customer\Api\Data\AddressInterface $address */
            $address = $this->addressFactory->create();

            $country = (string)$navAddressData->addr_country;
            $state = (string)$navAddressData->addr_state;
            $region = $this->addressRegionFactory->create();
            $region->setRegionCode($state)
                ->setRegion(null)
                ->setRegionId(null);

            $searchRegion = $this->directoryRegionFactory->create()->loadByCode($state, $country);
            if ($searchRegion && $searchRegion->getId()) {
                /** @var \Magento\Customer\Api\Data\RegionInterface $region */
                $region->setRegionCode($searchRegion->getCode())
                    ->setRegion($searchRegion->getName())
                    ->setRegionId($searchRegion->getRegionId());
            }

            $firstname = (string)$navAddressData->addr_name;
            $lastname = (string)$navAddressData->addr_name2;
            if (empty($firstname) || empty($lastname)) {
                $firstname = (string)$navAddressData->addr_first_name;
                $lastname = (string)$navAddressData->addr_last_name;
            }
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

            if (!isset($navAddressData->addr_company_name)) {
                $company = (string)$navAddressData->cust_company_name;
            } else {
                $company = (string)$navAddressData->addr_company_name;
            }
            if ($firstname == $company) {
                $company = '';
            }
            $telephone = (string)$navAddressData->addr_phone;
            $telephone = empty($telephone) ? 'N/A' : $telephone;
            $streetData = [];
            if (!empty($navAddressData->addr_street)) {
                $streetData[] = (string)$navAddressData->addr_street;
            }
            if (!empty($navAddressData->address_2)) {
                $streetData[] = (string)$navAddressData->address_2;
            }

            $isDefaultBilling = $this->isAddressDefaultBilling($navAddressData);
            $isDefaultShipping = $this->isAddressDefaultShipping($navAddressData);

            // Split NAV address mode, first create billing address
            $splitMode = false;
            if ($this->config->getWebsiteData('customer/split_nav_customer_address', $websiteId)
                && $isDefaultBilling && $isDefaultShipping
            ) {
                $isDefaultShipping = false;
                $splitMode = true;
            }

            $address
                ->setId(null)
                ->setCustomerId($customer->getId())
                ->setCompany($company)
                ->setCountryId($country)
                ->setPostcode((string)$navAddressData->addr_post_code)
                ->setRegion($region)
                ->setStreet($streetData)
                ->setTelephone($telephone)
                ->setCity((string)$navAddressData->addr_city)
                ->setFirstname($firstname)
                ->setLastname($lastname)
                ->setIsDefaultBilling($isDefaultBilling)
                ->setIsDefaultShipping($isDefaultShipping)
                ->setFax((string)$navAddressData->addr_fax)
                ->setRegionId($region->getRegionId())
                ->setCustomAttribute(
                    'nav_id',
                    $this->getSpeciallyMarkedAddressNavId($websiteId, (string)$navAddressData->addr_nav_id)
                );

            $result = $this->addressRepository->save($address);
            // Needed so that importCustomerAddresses() loop keep getting reloaded address objects from database with
            // latest customer data (otherwise for new customer default billing|shipping address IDs
            // will not be updated when loading address
            $this->addressRegistry->remove($address->getId());

            $defauFlagLabels = [];
            if ($isDefaultBilling) {
                $defauFlagLabels[] = 'billing';
            }
            if ($isDefaultShipping) {
                $defauFlagLabels[] = 'shipping';
            }
            $this->messages .= sprintf(
                 '--> Address "%s": CREATED%s' . PHP_EOL,
                (string)$navAddressData->addr_street,
                !empty($defauFlagLabels) ? ' as default ' . implode('/', $defauFlagLabels) : ''
            );

            // Create shipping address in split mode
            if ($splitMode) {
                $navAddressData->is_default_billing = 'false';

                return $this->createAddress($customer, $navAddressData, $websiteId);
            }

            return $result;
        } catch (\Throwable $e) {
            $this->messages .= sprintf(
                '--> Address "%s": ERROR - %s' . PHP_EOL,
                (string)$navAddressData->addr_street,
                $e->getMessage()
            );
        }

        return null;
    }

    /**
     * @param int    $websiteId
     * @param string $navIdValue
     *
     * @return string
     */
    protected function getSpeciallyMarkedAddressNavId($websiteId, $navIdValue)
    {
        return implode('|', [
            self::CUSTOMER_ADDRESS_SPECIAL_MARKER,
            $websiteId,
            $navIdValue
        ]);
    }

    /**
     * Check address data if is default billion
     *
     * @param \SimpleXMLElement $addressData
     *
     * @return bool
     */
    protected function isAddressDefaultBilling($addressData)
    {
        if (isset($addressData->is_default_billing)) {

            return filter_var($addressData->is_default_billing, FILTER_VALIDATE_BOOLEAN)
                   || filter_var($addressData->is_default_billing, FILTER_VALIDATE_INT) == 1;
        }

        return false;
    }

    /**
     * Check address data if is default shipping
     *
     * @param \SimpleXMLElement $addressData
     *
     * @return bool
     */
    protected function isAddressDefaultShipping($addressData)
    {
        if (isset($addressData->is_default_shipping)) {

            return filter_var($addressData->is_default_shipping, FILTER_VALIDATE_BOOLEAN)
                   || filter_var($addressData->is_default_shipping, FILTER_VALIDATE_INT) == 1;
        }

        return false;
    }
}
