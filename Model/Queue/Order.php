<?php
namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;

class Order extends \MalibuCommerce\MConnect\Model\Queue
{
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

    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \MalibuCommerce\MConnect\Model\Navision\Order $navOrder,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory

    ) {
        $this->orderRepository = $orderRepository;
        $this->navOrder = $navOrder;
        $this->orderFactory = $orderFactory;
        $this->queueFlagFactory = $queueFlagFactory;
    }

    public function exportAction($entityId = null)
    {
        try {
            $orderEntity = $this->orderRepository->get($entityId);
            $orderDataModel = $this->orderFactory->create()->load($entityId);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('Order ID "%1" does not exist', $entityId));
        } catch (\Exception $e) {
            throw new LocalizedException(__('Order ID "' . $entityId . '" loading error: %s', $e->getMessage()));
        }

        $response = $this->navOrder->import($orderEntity);
        $status = (string) $response->result->status;
        if ($status === 'Processed') {
            $navId = (string) $response->result->Order->nav_record_id;
            if ($orderDataModel->getNavId() != $navId) {
                $orderDataModel->setNavId($navId)
                    ->setSkipMconnect(true)
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