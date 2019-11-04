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


    public function __construct(
        \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory,
        \Magento\Rma\Model\RmaFactory $rmaModelFactory,
        \MalibuCommerce\MConnect\Model\Navision\Rma $navRma,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Eav\Api\AttributeRepositoryInterface $eavAttributeRepositoryInterface
    ) {
        $this->orderFactory = $orderFactory;
        $this->rmaModelFactory = $rmaModelFactory;
        $this->navRma = $navRma;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->eavAttributeRepository = $eavAttributeRepositoryInterface;
    }

    public function importAction($websiteId, $navPageNumber = 0)
    {
        return $this->processMagentoImport($this->navRma, $this, $websiteId, $navPageNumber);
    }

    public function importEntity(\SimpleXMLElement $data, $websiteId)
    {
        $data->mag_order_id = '000000008';
        if ((int)$data->mag_order_id) {
            $order = $this->orderFactory->create()->loadByIncrementId((string)$data->mag_order_id);

            /** @var RmaInterface $rmaModel */
            $rmaModel = $this->rmaModelFactory->create();
            $rmaModel->setData(
                [
                    'status' => $this->config->getDefaultRmaStatus($websiteId),
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
                //$orderItems[$product->getSku()] = $product->getItemId();
                $orderItems['663313'] = $product->getItemId();


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

        }

        return false;


    }


    /**
     * Return option Value
     *
     * @param int $optionValue
     *
     * @return string
     */
    public function getOptionId($attributeCode, $optionLabel)
    {
        $attribute = $this->eavAttributeRepository->get('rma_item', $attributeCode);

        foreach ($attribute->getOptions() as $option) {
            if (strtolower($option->getLabel()) == strtolower($optionLabel)) {
                return $option->getValue();
            }
        }

        return '';

    }
}