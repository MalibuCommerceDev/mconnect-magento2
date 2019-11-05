<?php

namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Rma\Model\Item;
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
     * Rma item factory
     *
     * @var \Magento\Rma\Model\ItemFactory
     */
    protected $rmaItemFactory;

    /**
     * Rma data
     *
     * @var \Magento\Rma\Helper\Data
     */
    protected $rmaData;

    /**
     * Escaper
     *
     * @var \Magento\Framework\Escaper
     */
    protected $escaper;

    /**
     * Rma item factory
     *
     * @var \Magento\Rma\Model\ResourceModel\ItemFactory
     */
    protected $itemFactory;

    /**
     * Serializer instance.
     *
     * @var Json
     */
    private $serializer;

    public function __construct(
        \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory,
        \Magento\Rma\Model\RmaFactory $rmaModelFactory,
        \MalibuCommerce\MConnect\Model\Navision\Rma $navRma,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Eav\Api\AttributeRepositoryInterface $eavAttributeRepositoryInterface,
        \Magento\Rma\Model\ItemFactory $rmaItemFactory,
        \Magento\Rma\Helper\Data $rmaData,
        \Magento\Framework\Escaper $escaper,
        \Magento\Rma\Model\ResourceModel\ItemFactory $itemFactory,
        Json $serializer = null
    ) {
        $objectManager = ObjectManager::getInstance();
        $this->orderFactory = $orderFactory;
        $this->rmaModelFactory = $rmaModelFactory;
        $this->navRma = $navRma;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->eavAttributeRepository = $eavAttributeRepositoryInterface;
        $this->rmaItemFactory = $rmaItemFactory;
        $this->rmaData = $rmaData;
        $this->escaper = $escaper;
        $this->itemFactory = $itemFactory;
        $this->serializer = $serializer ?: $objectManager->get(Json::class);


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

            if (!$post) {
                $this->messages .= 'No products found for RMA ';

                return false;
            }

            $result = $this->saveRmaItems($post, $order);
            if(is_string($result)) {
                $this->messages .= 'RMA not created, error: ' . $result;

                return false;
            } else {
                try {
                    $rmaModel->setItems($result)->save();
                    $this->messages .= 'RMA created';
                } catch (\Exception $e) {
                    $this->messages .= 'RMA doesn\'t created. Error: ' . $e->getMessage();
                }

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


    /**
     * Creates rma items collection by passed data
     *
     * @param array $data
     * @param object $order
     * @return Item[]
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function saveRmaItems($data, $order)
    {
        if (!is_array($data)) {
            $data = (array)$data;
        }
        $itemModels = [];
        $errors = [];
        $errorKeys = [];

        foreach ($data['items'] as $key => $item) {
            /** @var $itemModel Item */
            $itemModel = $this->rmaItemFactory->create();

            $itemPost = $this->_preparePost($item, $order);

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

        $result = $this->_checkPost($itemModels, $order->getId());

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
     * @param  Item $itemModels
     * @param  int $orderId
     * @return array|bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function _checkPost($itemModels, $orderId)
    {
        $errors = [];
        $errorKeys = [];

        /** @var $itemResource \Magento\Rma\Model\ResourceModel\Item */
        $itemResource = $this->itemFactory->create();
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
                'qty' => $item->getAvailableQty(),
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
     * @param array $item
     * @param object $order
     * @return array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function _preparePost($item, $order)
    {
        $errors = false;
        $preparePost = [];
        $qtyKeys = ['qty_authorized', 'qty_returned', 'qty_approved'];

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
        $preparePost['product_admin_name'] = $this->rmaData->getAdminProductName($realItem);
        $preparePost['product_admin_sku'] = $this->rmaData->getAdminProductSku($realItem);
        $preparePost['product_options'] = $this->serializer->serialize($realItem->getProductOptions());
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