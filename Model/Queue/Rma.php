<?php
namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;

class Rma extends \MalibuCommerce\MConnect\Model\Queue
{
    const CODE = 'rma';

    /**
     * @var \Magento\Rma\Api\RmaRepositoryInterface
     */
    protected $rmaoRepository;

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

    public function __construct(
        \Magento\Rma\Api\RmaRepositoryInterface $rmaoRepository,
        \MalibuCommerce\MConnect\Model\Navision\Rma $navRma,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->rmaRepository = $rmaoRepository;
        $this->navRma = $navRma;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    public function exportAction($entityId)
    {
        try {
            $rmaEntity = $this->rmaRepository->get($entityId);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('RMA ID "%1" does not exist', $entityId));
        } catch (\Exception $e) {
            throw new LocalizedException(__('RMA ID "' . $entityId . '" loading error: %1', $e->getMessage()));
        }

        $websiteId = $this->storeManager->getStore($rmaEntity->getStoreId())->getWebsiteId();

        $response = $this->navRma->import($rmaEntity, $websiteId);
        $status = (string) $response->result->status;

        if ($status === 'Processed') {
            $navId = (string) $response->result->RMA->nav_record_id;
            if ($navId) {
                $this->messages .= sprintf('RMA exported, NAV ID: %s', $navId);
            } else {
                $this->messages .= sprintf('RMA exported, NAV ID is empty');
            }

            return true;
        }

        if ($status == 'Error') {
            $errors = array();
            foreach ($response->result->RMA as $order) {
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