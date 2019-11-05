<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Rma\Model\Rma\Source\Status;

class Rma extends \MalibuCommerce\MConnect\Model\Queue implements ImportableEntity
{
    const CODE = 'rma';
    const NAV_XML_NODE_ITEM_NAME = 'rma';
    /**
     * @var \Magento\Sales\Api\Data\OrderInterfaceFactory
     */

    protected $orderFactory;

    /**
     * @var \Magento\Rma\Model\RmaFactory
     */
    protected $rmaModelFactory;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Rma
     */
    protected $navRma;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue\FlagFactory
     */
    protected $queueFlagFactory;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Eav\Api\AttributeRepositoryInterface
     */
    protected $eavAttributeRepository;

    /**
     * Message manager
     *
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;


    public function __construct(
        \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory,
        \Magento\Rma\Model\RmaFactory $rmaModelFactory,
        \MalibuCommerce\MConnect\Model\Navision\Rma $navRma,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Eav\Api\AttributeRepositoryInterface $eavAttributeRepositoryInterface,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->orderFactory = $orderFactory;
        $this->rmaModelFactory = $rmaModelFactory;
        $this->navRma = $navRma;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->eavAttributeRepository = $eavAttributeRepositoryInterface;
        $this->messageManager = $messageManager;
    }

    public function importAction($websiteId, $navPageNumber = 0)
    {
        return $this->processMagentoImport($this->navRma, $this, $websiteId, $navPageNumber);
    }

    public function importEntity(\SimpleXMLElement $data, $websiteId)
    {
        if ((int)$data->mag_order_id) {
            $order = $this->orderFactory->create()->loadByIncrementId((string)$data->mag_order_id);
            if (!$order->getId()) {
                $this->messages .= 'RMA not created, Magento orderID #' . (int)$data->mag_order_id . ' doesn\'t exist';

                return false;
            }
            /** @var RmaInterface $rmaModel */
            $rmaModel = $this->rmaModelFactory->create();
            $rmaModel->setData(
                [
                    'status' => current($this->config->getDefaultRmaStatus($websiteId)),
                    'date_requested' => (string)$data->posting_date,
                    'order_id' => $order->getId(),
                    'order_increment_id' => $order->getIncrementId(),
                    'store_id' => $order->getStoreId(),
                    'customer_id' => $order->getCustomerId(),
                    'order_date' => $order->getCreatedAt(),
                    'customer_name' => $order->getCustomerName(),
                    'customer_custom_email' => '',
                ]
            );

            $orderItems = [];
            foreach ($order->getAllVisibleItems() as $product) {
                $orderItems[$product->getSku()] = $product->getItemId();
            }

            foreach ($data->item as $item) {
                if (isset($orderItems[(string)$item->mag_sku])) {
                    $post['items'][] = [
                        'reason' => $this->getOptionId('reason', (string)$item->reason),
                        'condition' => $this->getOptionId('condition', (string)$item->condition),
                        'resolution' => $this->getOptionId('resolution', (string)$item->resolution),
                        'qty_requested' =>  (int)$item->qty,
                        'order_item_id' => $orderItems[(string)$item->mag_sku]
                    ];
                }

            }
            $result = $rmaModel->saveRma($post);
            if($result === false) {
                $message = $this->messageManager->getMessages(true)->getLastAddedMessage();
                $this->messages .= 'RMA not created, error: ' . $message->getText();

                return false;
            } else {
                $this->messages .= 'RMA ' . $result->getId() . ': created';

                return true;
            }
        }

        return false;
    }


    /**
     * Return option Value
     *
     * @param string $attributeCode
     * @param string $optionLabel
     *
     * @return string
     */
    public function getOptionId($attributeCode, $optionLabel)
    {
        $attribute = $this->eavAttributeRepository->get(\Magento\Rma\Api\RmaAttributesManagementInterface::ENTITY_TYPE, $attributeCode);

        foreach ($attribute->getOptions() as $option) {
            if (strtolower($option->getLabel()) == strtolower($optionLabel)) {

                return $option->getValue();
            }
        }

        return '';
    }
}