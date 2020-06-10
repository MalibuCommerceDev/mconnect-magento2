<?php

namespace MalibuCommerce\MConnect\Model\Queue;

class Rma extends \MalibuCommerce\MConnect\Model\Queue implements ImportableEntity
{
    const CODE                   = 'rma';
    const NAV_XML_NODE_ITEM_NAME = 'rma';

    /**
     * @var \Magento\Sales\Api\Data\OrderInterfaceFactory
     */
    protected $orderFactory;

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
     * Escaper
     *
     * @var \Magento\Framework\Escaper
     */
    protected $escaper;

    /**
     * @var \Magento\Framework\Module\Manager
     */
    protected $moduleManager;

    public function __construct(
        \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory,
        \MalibuCommerce\MConnect\Model\Navision\Rma $navRma,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Eav\Api\AttributeRepositoryInterface $eavAttributeRepositoryInterface,
        \Magento\Framework\Escaper $escaper,
        \Magento\Framework\Module\Manager $moduleManager
    ) {
        $this->orderFactory = $orderFactory;
        $this->navRma = $navRma;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->eavAttributeRepository = $eavAttributeRepositoryInterface;
        $this->escaper = $escaper;
        $this->moduleManager = $moduleManager;
    }

    /**
     * @param int $websiteId
     * @param int $navPageNumber
     *
     * @return bool|\Magento\Framework\DataObject|Rma
     * @throws \Exception
     */
    public function importAction($websiteId, $navPageNumber = 0)
    {
        if (!$this->moduleManager->isEnabled('Magento_Rma')) {

            return  false;
        }

        return $this->processMagentoImport($this->navRma, $this, $websiteId, $navPageNumber);
    }

    /**
     * @param \SimpleXMLElement $data
     * @param int $websiteId
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function importEntity(\SimpleXMLElement $data, $websiteId)
    {
        $orderIncrementId = (string)$data->mag_order_id;
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
        if (!$order || !$order->getId()) {
            throw new \LogicException(
                __('RMA cannot be processed, order #' . $orderIncrementId . ' does not exist')
            );
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $rmaModelFactory = $objectManager->create(\Magento\Rma\Model\RmaFactory::class);
        $rmaModel = $rmaModelFactory->create();
        $rmaModel->setData(
            [
                'status'                => current($this->config->getDefaultRmaStatus($websiteId)),
                'date_requested'        => (string)$data->posting_date,
                'order_id'              => $order->getId(),
                'order_increment_id'    => $order->getIncrementId(),
                'store_id'              => $order->getStoreId(),
                'customer_id'           => $order->getCustomerId(),
                'order_date'            => $order->getCreatedAt(),
                'customer_name'         => $order->getCustomerName(),
                'customer_custom_email' => '',
            ]
        );

        $orderItems = [];
        /** @var \Magento\Sales\Model\Order\Item $product */
        foreach ($order->getAllVisibleItems() as $product) {
            $orderItems[$product->getSku()] = $product->getItemId();
        }

        $rmaData = [];
        foreach ($data->item as $item) {
            if (empty($orderItems[(string)$item->mag_sku])) {
                continue;
            }

            $itemData = [
                'condition'     => $this->getOptionId('condition', (string)$item->condition),
                'resolution'    => $this->getOptionId('resolution', (string)$item->resolution),
                'qty_requested' => (int)$item->qty,
                'order_item_id' => $orderItems[(string)$item->mag_sku]
            ];

            $reason = (string)$item->reason;
            if (strtolower($reason) == 'other') {
                $itemData['reason_other'] = $reason;
            } else {
                $itemData['reason'] = $this->getOptionId('reason', (string)$item->reason);
            }

            $rmaData['items'][] = $itemData;
        }

        if (!$rmaData) {
            throw new \LogicException(
                __('Can\'t create an RMA for order #%1 - no available items to return.', $orderIncrementId)
            );
        }

        $result = $this->createRmaItemModels($rmaData, $order);
        if (is_string($result)) {
            throw new \LogicException(
                __('Can\'t create an RMA for order #%1 - error "%2".', $orderIncrementId, $result)
            );
        }

        try {
            $rmaModel->setItems($result)->save();

            $this->messages .= 'RMA created: #' . $rmaModel->getIncrementId();
        } catch (\Throwable $e) {
            throw new \LogicException('RMA was not created: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Return option Value
     *
     * @param string $attributeCode
     * @param string $optionLabel
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getOptionId($attributeCode, $optionLabel)
    {
        $attribute = $this->eavAttributeRepository->get(
            \Magento\Rma\Api\RmaAttributesManagementInterface::ENTITY_TYPE,
            $attributeCode
        );

        foreach ($attribute->getOptions() as $option) {
            if (strtolower($option->getLabel()) == strtolower($optionLabel)) {

                return $option->getValue();
            }
        }

        return '';
    }

    /**
     * Creates rma items collection by passed data
     *
     * @param array  $data
     * @param object $order
     *
     * @return \Magento\Rma\Model\Item[]
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function createRmaItemModels($data, $order)
    {
        if (!is_array($data)) {
            $data = (array)$data;
        }

        /** @var \Magento\Rma\Model\Item[] $itemModels */
        $itemModels = [];
        $errors = [];
        $errorKeys = [];

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $rmaItemFactory = $objectManager->create(\Magento\Rma\Model\ItemFactory::class);
        foreach ($data['items'] as $key => $item) {
            /** @var $itemModel \Magento\Rma\Model\Item */
            $itemModel = $rmaItemFactory->create();

            $itemPost = $this->prepareRmaItemData($item, $order);

            if (!is_array($itemPost)) {

                // return error string
                return $itemPost;
            }

            $itemModel->setData($itemPost)->prepareAttributes($itemPost, $key);
            $errors = array_merge($itemModel->getErrors(), $errors);
            if ($errors) {
                $errorKeys['tabs'] = 'items_section';
            }

            $itemModels[] = $itemModel;
        }

        $result = $this->checkRmaItems($itemModels, $order->getId());

        if ($result !== true) {
            list($result, $errorKey) = $result;
            $errors = array_merge($result, $errors);
            $errors = implode(',', $errors);

            // return error string
            return $errors;
        }

        return $itemModels;
    }

    /**
     * Checks Items Quantity in Return
     *
     * @param \Magento\Rma\Model\Item[] $itemModels
     * @param int  $orderId
     *
     * @return array|bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function checkRmaItems($itemModels, $orderId)
    {
        $errors = [];
        $errorKeys = [];
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $itemFactory = $objectManager->create(\Magento\Rma\Model\ResourceModel\ItemFactory::class);

        /** @var $itemResource \Magento\Rma\Model\ResourceModel\Item */
        $itemResource = $itemFactory->create();
        $availableItems = $itemResource->getOrderItemsCollection($orderId);

        $itemsArray = [];
        foreach ($itemModels as $item) {
            if (!isset($itemsArray[$item->getOrderItemId()])) {
                $itemsArray[$item->getOrderItemId()] = $item->getQtyRequested();
            } else {
                $itemsArray[$item->getOrderItemId()] += $item->getQtyRequested();
            }
        }
        ksort($itemsArray);

        $availableItemsArray = [];
        foreach ($availableItems as $item) {
            $availableItemsArray[$item->getId()] = [
                'name' => $item->getName(),
                'qty'  => $item->getAvailableQty(),
            ];
        }

        foreach ($itemsArray as $key => $qty) {
            $escapedProductName = $this->escaper->escapeHtml($availableItemsArray[$key]['name']);
            if (!array_key_exists($key, $availableItemsArray)) {
                $errors[] = __('You cannot return %1.', $escapedProductName);
            }
            if (isset($availableItemsArray[$key]) && $availableItemsArray[$key]['qty'] < $qty) {
                $errors[] = __('A quantity of %1 is greater than you can return.', $escapedProductName);
                $errorKeys[$key] = 'qty_requested';
                $errorKeys['tabs'] = 'items_section';
            }
        }

        if (!empty($errors)) {
            
            return [$errors, $errorKeys];
        }

        return true;
    }

    /**
     * Prepares Item's data
     *
     * @param array  $item
     * @param object $order
     *
     * @return array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function prepareRmaItemData($item, $order)
    {
        $errors = false;
        $preparePost = [];
        $qtyKeys = ['qty_authorized', 'qty_returned', 'qty_approved'];
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $rmaData = $objectManager->create(\Magento\Rma\Helper\Data::class);

        ksort($item);
        foreach ($item as $key => $value) {
            if ($key == 'order_item_id') {
                $preparePost['order_item_id'] = (int)$value;
            } elseif ($key == 'qty_requested') {
                $preparePost['qty_requested'] = is_numeric($value) ? $value : 0;
            } elseif (in_array($key, $qtyKeys)) {
                if (is_numeric($value)) {
                    $preparePost[$key] = (double)$value;
                } else {
                    $preparePost[$key] = '';
                }
            } elseif ($key == 'resolution') {
                $preparePost['resolution'] = (int)$value;
            } elseif ($key == 'condition') {
                $preparePost['condition'] = (int)$value;
            } elseif ($key == 'reason') {
                $preparePost['reason'] = (int)$value;
            } elseif ($key == 'reason_other' && !empty($value)) {
                $preparePost['reason_other'] = $value;
            } else {
                $preparePost[$key] = $value;
            }
        }

        $realItem = $order->getItemById($preparePost['order_item_id']);

        $preparePost['status'] = \Magento\Rma\Model\Item\Attribute\Source\Status::STATE_PENDING;
        $preparePost['entity_id'] = null;
        $preparePost['product_name'] = $realItem->getName();
        $preparePost['product_sku'] = $realItem->getSku();
        $preparePost['product_admin_name'] = $rmaData->getAdminProductName($realItem);
        $preparePost['product_admin_sku'] = $rmaData->getAdminProductSku($realItem);
        $preparePost['product_options'] = json_encode($realItem->getProductOptions());
        $preparePost['is_qty_decimal'] = $realItem->getIsQtyDecimal();

        if ($preparePost['is_qty_decimal']) {
            $preparePost['qty_requested'] = (double)$preparePost['qty_requested'];
        } else {
            $preparePost['qty_requested'] = (int)$preparePost['qty_requested'];

            foreach ($qtyKeys as $key) {
                if (!empty($preparePost[$key])) {
                    $preparePost[$key] = (int)$preparePost[$key];
                }
            }
        }

        if (isset($preparePost['qty_requested']) && $preparePost['qty_requested'] <= 0) {
            $errors = true;
        }

        foreach ($qtyKeys as $key) {
            if (isset($preparePost[$key]) && !is_string($preparePost[$key]) && $preparePost[$key] <= 0) {
                $errors = true;
            }
        }

        if ($errors) {
            return __('There is an error in quantities for item %1.', $preparePost['product_name']);
        }

        return $preparePost;
    }
}
