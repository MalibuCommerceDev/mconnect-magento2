<?php
namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;

class Order extends \MalibuCommerce\MConnect\Model\Queue
{
    const CODE = 'order';

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Order
     */
    protected $navOrder;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

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

    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \MalibuCommerce\MConnect\Model\Navision\Order $navOrder,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->orderRepository = $orderRepository;
        $this->navOrder = $navOrder;
        $this->orderFactory = $orderFactory;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    public function exportAction($entityId)
    {
        try {
            $orderEntity = $this->orderRepository->get($entityId);
            $orderDataModel = $this->orderFactory->create()->load($entityId);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('Order ID "%1" does not exist', $entityId));
        } catch (\Exception $e) {
            throw new LocalizedException(__('Order ID "' . $entityId . '" loading error: %1', $e->getMessage()));
        }

        $websiteId = $this->storeManager->getStore($orderEntity->getStoreId())->getWebsiteId();
        if (!in_array($orderEntity->getStatus(), $this->config->getOrderStatuesAllowedForExportToNav($websiteId))) {
            $message = sprintf('Order "%s" (ID: %s) was not exported because its status is: %s', $orderEntity->getIncrementId(), $entityId, $orderEntity->getStatus());
            throw new \LogicException($message);
        }

        $response = $this->navOrder->import($orderEntity, $websiteId);
        $status = (string) $response->result->status;

        if ($status === 'Processed') {
            $navId = (string) $response->result->Order->nav_record_id;
            if ($orderDataModel->getNavId() != $navId) {
                if (!$orderDataModel->getNavId() && $this->config->getIsHoldNewOrdersExport($websiteId)) {
                    $newStatus = $this->config->getOrderStatusWhenSyncedToNav($websiteId);
                    $orderState = \Magento\Sales\Model\Order::STATE_NEW;
                    $orderDataModel->setState($orderState)
                        ->setStatus($newStatus);
                    $orderDataModel->addStatusToHistory($orderDataModel->getStatus(), 'Order exported to NAV successfully with reference ID ' . $navId);
                }
                $orderDataModel->setNavId($navId);
                $orderDataModel->setSkipMconnect(true)
                    ->save();
            }
            $this->messages .= sprintf('Order exported, NAV ID: %s', $navId);

            return true;
        }

        if ($status == 'Error') {
            $errors = array();
            foreach ($response->result->Order as $order) {
                foreach ($order->error as $error) {
                    $errors[] = (string) $error->message;
                }
            }
            if (empty($errors)) {
                $errors[] = 'Unknown API error.';
            }
            $this->messages .= implode("\n", $errors);

            throw new \Exception(implode("\n", $errors));
        }

        throw new LocalizedException(__('Unexpected status: "%1". Check log for details.', $status));
    }
}