<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace MalibuCommerce\MConnect\Observer;

use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Helper\Address as HelperAddress;
use Magento\Customer\Model\Address\AbstractAddress;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State as AppState;
use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\Vat;

/**
 * Customer Observer Model
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AfterCustomerSaveObserver implements ObserverInterface
{
    public function __construct(
        \MalibuCommerce\MConnect\Model\Queue $mConnectQueue,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->mConnectQueue = $mConnectQueue;
        $this->logger = $logger;
    }

    /**
     * Address after save event handler
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $object = $observer->getCustomer();
        if (!$object->getSkipMconnect()) {
            $this->_queue('customer', 'export', $object->getId());
        }
    }

    protected function _queue($code, $action, $id = null, $details = array())
    {
        try {
            return $this->mConnectQueue->add($code, $action, $id, $details);
        } catch (Exception $e) {
            $this->logger->critical($e);
        }
        return false;
    }
}
