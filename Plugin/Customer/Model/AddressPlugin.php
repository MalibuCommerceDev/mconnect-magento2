<?php

namespace MalibuCommerce\MConnect\Plugin\Customer\Model;

use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Model\Address as Subject;

class AddressPlugin
{
    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @param \MalibuCommerce\MConnect\Model\Config $config
     */
    public function __construct(\MalibuCommerce\MConnect\Model\Config $config)
    {
        $this->config = $config;
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
            $resultAddressModel
                ->setNavId($specialNavId[2] ?? 'DEFAULT')
                ->setShouldIgnoreValidation($ignoreValidation)
                ->setSkipMconnect(true);
        }

        return $resultAddressModel;
    }
}
