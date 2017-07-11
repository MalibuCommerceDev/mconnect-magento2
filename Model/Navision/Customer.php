<?php
namespace MalibuCommerce\MConnect\Model\Navision;


class Customer extends \MalibuCommerce\MConnect\Model\Navision\AbstractModel
{

    /**
     * @var \Magento\Directory\Model\Region
     */
    protected $directoryRegion;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    public function __construct(
        \Magento\Directory\Model\Region $directoryRegion,
        \MalibuCommerce\MConnect\Model\Config $config,
        \MalibuCommerce\MConnect\Model\Navision\Connection $mConnectNavisionConnection
    ) {
        $this->directoryRegion = $directoryRegion;
        $this->config = $config;

        parent::__construct(
            $mConnectNavisionConnection
        );
    }

    public function import(\Magento\Customer\Api\Data\CustomerInterface $customer)
    {
        $cust = new \simpleXMLElement('<Customer />');
        //$cust->nav_customer_id = "";
        $cust->mag_customer_id = $customer->getId();
        $cust->first_name      = $customer->getFirstname();
        $cust->last_name       = $customer->getLastname();
        //$cust->company_name = "";
        $cust->email_address   = $customer->getEmail();
        $cust->store_id        = $customer->getStoreId();

        $defaultBillingAddressId  = $customer->getDefaultBilling();
        $defaultShipingAddressId = $customer->getDefaultShipping();

        foreach ($customer->getAddresses() as $address) {
            $address->setIsDefaultBilling($defaultBillingAddressId == $address->getId());
            $address->setIsDefaultShipping($defaultShipingAddressId == $address->getId());
            $this->_addAddress($address, $cust);
        }
        
        return $this->_import('customer_import', $cust);
    }

    protected function _addAddress(\Magento\Customer\Api\Data\AddressInterface $address, &$cust)
    {
        $child  = $cust->addChild('customer_address');
        $street = $address->getStreet();

        //$child->nav_address_id      = '';
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

    public function export($page = 0, $lastUpdated = false)
    {
        $config = $this->config;
        $max    = $config->get('customer/max_rows');
        $parameters = array(
            'skip'     => $page * $max,
            'max_rows' => $max,
        );
        if ($lastUpdated) {
            $parameters['last_updated'] = $lastUpdated;
        }
        return $this->_export('customer_export', $parameters);
    }
}
