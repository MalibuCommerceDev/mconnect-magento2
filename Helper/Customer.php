<?php

namespace MalibuCommerce\MConnect\Helper;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class Customer extends AbstractHelper
{
    /**
     * @var CustomerInterface
     */
    protected $customer;

    /**
     * @var CustomerRegistry
     */
    protected $customerRegistry;

    /**
     * @var GroupRepositoryInterface
     */
    protected $groupRepository;

    /**
     * @var SessionFactory
     */
    protected $customerSessionFactory;

    public function __construct(
        CustomerRegistry $customerRegistry,
        GroupRepositoryInterface $groupRepository,
        SessionFactory $customerSessionFactory,
        Context $context
    ) {
        $this->customerRegistry = $customerRegistry;
        $this->customerSessionFactory = $customerSessionFactory;
        $this->groupRepository = $groupRepository;

        parent::__construct($context);
    }

    /**
     * Return logged in customer model
     *
     * @return CustomerInterface|\Magento\Customer\Model\Customer|null
     */
    public function getCurrentCustomer()
    {
        if (!$this->customer) {
            $customer = $this->customerSessionFactory->create();
            if ($customer->getCustomer() && $customer->getCustomer()->getId()) {
                try {
                    $this->customer = $this->customerRegistry->retrieve($customer->getCustomer()->getId());
                } catch (\Throwable $e) {
                    return null;
                }
            } else {
                return null;
            }
        }

        return $this->customer;
    }

    /**
     * Retrieve current customer group code
     *
     * @return null|string
     */
    public function getCurrentCustomerGroup()
    {
        $groupCode = null;

        try {
            $groupCode = $this->groupRepository->getById($this->getCurrentCustomerGroupId())->getCode();
        } catch (\Throwable $e) {

        }

        return $groupCode;
    }

    /**
     * Retrieve current customer group id
     *
     * @return int
     */
    public function getCurrentCustomerGroupId()
    {
        $groupId = Group::NOT_LOGGED_IN_ID;

        if ($this->getCurrentCustomer()) {

            return $this->getCurrentCustomer()->getGroupId();
        }

        return $groupId;
    }

    /**
     * @return int|null
     */
    public function getCurrentCustomerId()
    {
        $customerId = Group::NOT_LOGGED_IN_ID;;
        $customer = $this->getCurrentCustomer();
        if (!$customer) {

            return $customerId;
        }

        $customerId = $customer->getId();

        return $customerId;
    }
}
