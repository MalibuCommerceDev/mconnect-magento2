<?php
namespace MalibuCommerce\MConnect\Model\Queue;


class Customer extends \MalibuCommerce\MConnect\Model\Queue
{

    /**
     * @var \Magento\Customer\Model\Customer
     */
    protected $customerCustomer;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Customer
     */
    protected $mConnectNavisionCustomer;

    /**
     * @var \MalibuCommerce\MConnect\Helper\Data
     */
    protected $mConnectHelper;

    /**
     * @var \Magento\Customer\Model\ResourceModel\Customer\Collection
     */
    protected $customerResourceModelCustomerCollection;

    /**
     * @var \Magento\Customer\Model\ResourceModel\Address\Collection
     */
    protected $customerResourceModelAddressCollection;

    /**
     * @var \Magento\Customer\Model\Address
     */
    protected $customerAddress;

    /**
     * @var \Magento\Directory\Model\Country
     */
    protected $directoryCountry;

    protected $mConnectConfig;

    public function __construct(
        \Magento\Customer\Model\Customer $customerCustomer,
        \MalibuCommerce\MConnect\Model\Navision\Customer $mConnectNavisionCustomer,
        \MalibuCommerce\MConnect\Helper\Data $mConnectHelper,
        \Magento\Customer\Model\ResourceModel\Customer\Collection $customerResourceModelCustomerCollection,
        \Magento\Customer\Model\ResourceModel\Address\Collection $customerResourceModelAddressCollection,
        \Magento\Customer\Model\Address $customerAddress,
        \Magento\Directory\Model\Country $directoryCountry,
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig
    ) {
        $this->customerCustomer = $customerCustomer;
        $this->mConnectNavisionCustomer = $mConnectNavisionCustomer;
        $this->mConnectHelper = $mConnectHelper;
        $this->customerResourceModelCustomerCollection = $customerResourceModelCustomerCollection;
        $this->customerResourceModelAddressCollection = $customerResourceModelAddressCollection;
        $this->customerAddress = $customerAddress;
        $this->directoryCountry = $directoryCountry;
        $this->mConnectConfig = $mConnectConfig;
    }

    public function exportAction($entityId = null)
    {
        $customer = $this->customerCustomer->load($entityId);
        if (!$customer || !$customer->getId()) {
            throw new \Exception(sprintf('Customer ID "%s" does not exist.', $entityId));
        }
        $response = $this->mConnectNavisionCustomer->import($customer);
        if ((string) $response->Status === 'OK') {
            $this->_messages .= 'Document No: ' . (string) $response->DocumentNo;
        }
    }

    public function importAction()
    {
        $count       = 0;
        $page        = 0;
        $lastSync    = false;
        $lastUpdated = $this->getLastSync('customer');
        do {
            $result = $this->mConnectNavisionCustomer->export($page++, $lastUpdated);
            foreach ($result->customer as $data) {
                $count++;
                $this->_importCustomer($data);
            }
            if (!$lastSync) {
                $lastSync = $result->status->current_date_time;
            }
        } while (isset($result->status->end_of_records) && (string) $result->status->end_of_records === 'false');
        $this->setLastSync('customer', $lastSync);
        $this->_messages .= PHP_EOL . 'Processed ' . $count . ' customer(s).';
    }

    protected function _importCustomer($data)
    {
        $email = (string) $data->cust_email;
        if (empty($email)) {
            $this->_messages .= 'Skipping NAV ID "' . $data->cust_nav_id . '", email empty' . PHP_EOL;
            return;
        }
        $customer = $this->_prepareCustomerModel($data);
        $newCustomer = (bool) $customer->getId();
        $customer->addData($this->_prepareImportData($data));
        try {
            $customer->save();
            $this->_messages .= $email . ': saved' . PHP_EOL;
            if ($newCustomer) {
                if ($this->mConnectHelper->sendNewCustomerEmail($customer)) {
                    $this->_messages .= $email . ': new customer email sent' . PHP_EOL;
                }
            }
        } catch (Exception $e) {
            $this->_messages .= $email . ': ' . $e->getMessage() . PHP_EOL;
        }
        if ($data->address) {
            foreach ($data->address as $address) {
                $this->_importAddress($customer, $address);
            }
        }
    }

    protected function _prepareCustomerModel($data)
    {
        $email = (string) $data->cust_email;
        $row = $this->customerResourceModelCustomerCollection->addFieldToFilter('email', $email)
            ->getFirstItem();
        $customer = $this->customerCustomer;
        if ($row && $row->getId()) {
            $customer->load($row->getId());
        } else {
            $customer
                ->setEmail($email)
                ->setGroupId(
                    !$data->cust_taxable || $data->cust_taxable == 'false'
                    ? $this->mConnectConfig->get('customer/default_group_nontaxable')
                    : $this->mConnectConfig->get('customer/default_group_taxable')
                )
                ->setWebsiteId($this->mConnectConfig->get('customer/default_website'))
            ;
        }
        return $customer;
    }

    protected function _prepareImportData($customer)
    {
        $data = array();
        $data['skip_mconnect'] = true;
        $data['firstname'] = (string) $customer->cust_name;
        $data['lastname'] = (string) $customer->cust_name2;
        return $data;
    }

    protected function _importAddress($customer, $data)
    {
        $address = false;
        if ($data->addr_nav_id) {
            $address = $this->customerResourceModelAddressCollection->addAttributeToFilter('parent_id', $customer->getId())
                ->addAttributeToFilter('nav_id', $data->addr_nav_id)
                ->getFirstItem();
        }
        if (!$address || !$address->getId()) {
            $address = $this->customerAddress;
        }
        $region = $this->_getRegion((string) $data->addr_country, (string) $data->addr_state);
        $address
            ->setParentId($customer->getId())
            ->setFirstname($data->addr_name)
            ->setLastname($data->addr_name2)
            ->setStreet($data->addr_street)
            ->setCity($data->addr_city)
            ->setRegion($region)
            ->setCountryId($data->addr_country)
            ->setPostcode($data->addr_post_code)
            ->setTelephone($data->addr_phone)
            ->setNavId($data->addr_nav_id)
            ->setSkipMconnect(true)
        ;
        if ($data->addr_nav_id == 'DEFAULT') {
            $address->setIsDefaultBilling(true);
            $address->setIsDefaultShipping(true);
        }
        try {
            $address->save();
            $this->_messages .= 'Address ' . $address->addr_nav_id . ': saved' . PHP_EOL;
        } catch (Exception $e) {
            $this->_messages .= 'Address ' . $address->addr_nav_id . ': ' . $e->getMessage() . PHP_EOL;
        }
    }

    protected function _getRegion($country, $state)
    {
        $country = $this->directoryCountry->load($country);
        if (!$country || !$country->getCountryId()) {
            return $state;
        }
        $region = $country->getRegionCollection()->addFieldToFilter('code', $state)->getFirstItem();
        if (!$region || !$region->getRegionId()) {
            return $state;
        }
        return $region->getRegionId();
    }
}
