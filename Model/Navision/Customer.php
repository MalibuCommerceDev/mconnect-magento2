<?php
namespace MalibuCommerce\MConnect\Model\Navision;

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
        $root = new \simpleXMLElement('<customer_import />');
        $exportXml = $root->addChild('Customer');
        $exportXml->nav_customer_id = $customerDataModel->getNavId();
        $exportXml->mag_customer_id = $customer->getId();
        $exportXml->first_name      = $customer->getFirstname();
        $exportXml->last_name       = $customer->getLastname();
        $exportXml->email_address   = $customer->getEmail();
        $exportXml->store_id        = $customer->getStoreId();
        $exportXml->customer_group  = $this->getCustomerGroupCodeById($customer->getGroupId());

        $exportXml = $this->setCustomCustomerAttributes($customer, $customerDataModel, $exportXml);

        $defaultBillingAddressId  = $customer->getDefaultBilling();
        $defaultShippingAddressId = $customer->getDefaultShipping();

        foreach ($customer->getAddresses() as $address) {
            $address->setIsDefaultBilling($defaultBillingAddressId == $address->getId());
            $address->setIsDefaultShipping($defaultShippingAddressId == $address->getId());
            $this->addAddress($address, $exportXml);
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

    protected function addAddress(\Magento\Customer\Api\Data\AddressInterface $address, \simpleXMLElement &$exportXml)
    {
        $child  = $exportXml->addChild('customer_address');
        $street = $address->getStreet();

        $navId                      = $address->getCustomAttribute('nav_id');
        $navId                      = $navId ? $navId->getValue() : null;
        $child->nav_address_id      = empty($navId) ? 'default' : $navId;
        $child->mag_address_id      = $address->getId();
        $child->is_default_billing  = $address->isDefaultBilling();
        $child->is_default_shipping = $address->isDefaultShipping();
        $child->first_name          = $address->getFirstname();
        $child->last_name           = $address->getLastname();
        $child->address_1           = $street[0];
        $child->address_2           = isset($street[1]) ? $street[1] : '';
        $child->city                = $address->getCity();
        $child->state               = $this->directoryRegion->load($address->getRegionId())->getCode();
        $child->post_code           = $address->getPostcode();
        $child->country             = $address->getCountryId();
        $child->telephone           = $address->getTelephone();
        $child->fax                 = $address->getFax();
    }

    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {
        $max = $this->config->get(\MalibuCommerce\MConnect\Model\Queue\Customer::CODE . '/max_rows');
        $parameters = [
            'skip'     => $page * $max,
            'max_rows' => $max,
        ];
        if ($lastUpdated) {
            $parameters['last_updated'] = $lastUpdated;
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

        $groupId = $groupId ?: \Magento\Customer\Model\Group::NOT_LOGGED_IN_ID;

        try {
            $groupCode = $this->groupRepository->getById($groupId)->getCode();
        } catch (\Throwable $e) {

        }

        return $groupCode;
    }
}
