<?php
namespace MalibuCommerce\MConnect\Model\Queue;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;

class Creditmemo extends \MalibuCommerce\MConnect\Model\Queue
{
    const CODE = 'creditmemo';

    /**
     * @var \Magento\Sales\Api\CreditmemoRepositoryInterface
     */
    protected $creditmemoRepository;

    /**
     * @var \MalibuCommerce\MConnect\Model\Navision\Creditmemo
     */
    protected $navCreditMemo;

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
        \Magento\Sales\Api\CreditmemoRepositoryInterface $creditmemoRepository,
        \MalibuCommerce\MConnect\Model\Navision\Creditmemo $navCreditMemo,
        \MalibuCommerce\MConnect\Model\Queue\FlagFactory $queueFlagFactory,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->creditmemoRepository = $creditmemoRepository;
        $this->navCreditMemo = $navCreditMemo;
        $this->queueFlagFactory = $queueFlagFactory;
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    public function exportAction($entityId)
    {
        try {
            $creditMemoEntity = $this->creditmemoRepository->get($entityId);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('Creditmemo ID "%1" does not exist', $entityId));
        } catch (\Exception $e) {
            throw new LocalizedException(__('Creditmemo ID "' . $entityId . '" loading error: %1', $e->getMessage()));
        }

        $websiteId = $this->storeManager->getStore($creditMemoEntity->getStoreId())->getWebsiteId();

        $response = $this->navCreditMemo->import($creditMemoEntity, $websiteId);
        $status = (string) $response->result->status;

        if ($status === 'Processed') {
            $navId = (string) $response->result->creditMemo->nav_record_id;
            if ($navId) {
                $this->messages .= sprintf('CreditMemo exported, NAV ID: %s', $navId);
            } else {
                $this->messages .= sprintf('CreditMemo exported, NAV ID is empty');
            }

            return true;
        }

        if ($status == 'Error') {
            $errors = array();
            foreach ($response->result->Creditmemo as $order) {
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