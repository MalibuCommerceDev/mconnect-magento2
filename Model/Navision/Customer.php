<?php

namespace MalibuCommerce\MConnect\Model\Navision;

use \MalibuCommerce\MConnect\Model\Queue\Customer as CustomerQueue;
use MalibuCommerce\MConnect\Model\SimpleXMLExtended;

class Customer extends AbstractModel
{
    /**
     * @var \Magento\Directory\Model\Region
     */
    protected $directoryRegion;

    /**
     * @var \Magento\Customer\Api\GroupRepositoryInterface
     */
    protected $groupRepository;

    /**
     * Customer constructor.
     *
     * @param \Magento\Directory\Model\Region                $directoryRegion
     * @param \MalibuCommerce\MConnect\Model\Config          $config
     * @param Connection                                     $mConnectNavisionConnection
     * @param \Psr\Log\LoggerInterface                       $logger
     * @param \Magento\Customer\Api\GroupRepositoryInterface $groupRepository
     * @param array                                          $data
     */
    public function __construct(
        \Magento\Directory\Model\Region $directoryRegion,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Navision\Connection $mConnectNavisionConnection,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Customer\Api\GroupRepositoryInterface $groupRepository,
        array $data = []
    ) {
        $this->directoryRegion = $directoryRegion;
        $this->groupRepository = $groupRepository;

        parent::__construct($config, $mConnectNavisionConnection, $logger);
    }

    public function import(
        \Magento\Customer\Api\Data\CustomerInterface $customer,
        \Magento\Customer\Model\Customer $customerDataModel,
        $websiteId
    ) {
        $root = new SimpleXMLExtended('<customer_import />');
        $exportXml = $root->addChild('Customer');
        $exportXml->nav_customer_id = $customerDataModel->getNavId();
        $exportXml->mag_customer_id = $customer->getId();

        if ($this->config->isCdataEnabledInExportXML($websiteId)) {
            $exportXml->addChild('first_name')->addCData($customer->getFirstname());
            $exportXml->addChild('last_name')->addCData($customer->getLastname());
        } else {
            $exportXml->first_name = $customer->getFirstname();
            $exportXml->last_name = $customer->getLastname();
        }

        $exportXml->email_address = $customer->getEmail();
        $exportXml->store_id = $customer->getStoreId();
        $exportXml->customer_group = $this->getCustomerGroupCodeById($customer->getGroupId());

        $exportXml = $this->setCustomCustomerAttributes($customer, $customerDataModel, $exportXml);

        $defaultBillingAddressId = $customer->getDefaultBilling();
        $defaultShippingAddressId = $customer->getDefaultShipping();

        foreach ($customer->getAddresses() as $address) {
            $address->setIsDefaultBilling($defaultBillingAddressId == $address->getId());
            $address->setIsDefaultShipping($defaultShippingAddressId == $address->getId());
            $this->addAddress($address, $exportXml, $websiteId);
        }

        return $this->_import('customer_import', $root, $websiteId);
    }

    public function setCustomCustomerAttributes(
        \Magento\Customer\Api\Data\CustomerInterface $customer,
        \Magento\Customer\Model\Customer $customerDataModel,
        \simpleXMLElement $exportXml
    ) {
        return $exportXml;
    }

    /**
     * @param \Magento\Customer\Api\Data\AddressInterface $address
     * @param \simpleXMLElement                           $exportXml
     * @param int                                         $websiteId
     */
    protected function addAddress(
        \Magento\Customer\Api\Data\AddressInterface $address,
        \simpleXMLElement &$exportXml,
        $websiteId = 0
    ) {
        $child = $exportXml->addChild('customer_address');
        $street = $address->getStreet();

        $navId = $address->getCustomAttribute('nav_id');
        $navId = $navId ? $navId->getValue() : null;
        $child->nav_address_id = empty($navId) ? 'DEFAULT' : $navId;
        $child->mag_address_id = $address->getId();
        $child->is_default_billing = $address->isDefaultBilling();
        $child->is_default_shipping = $address->isDefaultShipping();

        if ($this->config->isCdataEnabledInExportXML($websiteId)) {
            $child->addChild('first_name')->addCData($address->getFirstname());
            $child->addChild('last_name')->addCData($address->getLastname());
            $child->addChild('company_name')->addCData($address->getCompany());
        } else {
            $child->first_name = $address->getFirstname();
            $child->last_name = $address->getLastname();
            $child->company_name = $address->getCompany();
        }

        $child->address_1 = $street[0];
        $child->address_2 = isset($street[1]) ? $street[1] : '';
        $child->city = $address->getCity();
        $child->state = $this->directoryRegion->load($address->getRegionId())->getCode();
        $child->post_code = $address->getPostcode();
        $child->country = $address->getCountryId();
        $child->telephone = preg_replace('~[^0-9]+~', '', $address->getTelephone());
        $child->fax = $address->getFax();
    }

    /**
     * @param int   $page
     * @param false $lastUpdated
     * @param int   $websiteId
     *
     * @return \simpleXMLElement
     * @throws \Throwable
     */
    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {
        $max = $this->config->get(CustomerQueue::CODE . '/max_rows');
        $parameters = [
            'skip'     => $page * $max,
            'max_rows' => $max,
        ];
        if ($lastUpdated) {
            $parameters['last_updated'] = $lastUpdated;
        }
        if (!empty($this->config->get(CustomerQueue::CODE . '/import_enabled_only'))) {
            $parameters['web_enabled'] = true;
        }

        return $this->_export('customer_export', $parameters, $websiteId);
    }

    /**
     * @param string|int $groupId
     *
     * @return null|string
     */
    protected function getCustomerGroupCodeById($groupId)
    {
        $groupCode = null;

        $groupId = $groupId ? : \Magento\Customer\Model\Group::NOT_LOGGED_IN_ID;

        try {
            $groupCode = $this->groupRepository->getById($groupId)->getCode();
        } catch (\Throwable $e) {

        }

        return $groupCode;
    }
}
