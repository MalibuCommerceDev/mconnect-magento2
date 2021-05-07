<?php

declare(strict_types=1);

namespace MalibuCommerce\MConnect\Plugin;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ProductMetadataInterface;

class DisableVoid
{
    /**
     * @var \Magento\Braintree\Gateway\SubjectReader|\PayPal\Braintree\Gateway\Helper\SubjectReader
     */
    protected $subjectReader;

    /**
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(
        ProductMetadataInterface $productMetadata
    ) {
        $this->subjectReader = version_compare($productMetadata->getVersion(), '2.4.1', '>=')
            ? ObjectManager::getInstance()->get('\PayPal\Braintree\Gateway\Helper\SubjectReader')
            : ObjectManager::getInstance()->get('\Magento\Braintree\Gateway\SubjectReader');
    }

    /**
     * Disable void
     *
     * @param $pluginSubject
     * @param bool $result
     * @param array $subject
     * @param int|null $storeId
     *
     * @return bool
     */
    public function afterHandle(
        $pluginSubject,
        bool $result,
        array $subject,
        ?int $storeId = null
    ): bool {
        $payment = $this->subjectReader->readPayment($subject);

        return $result && !$payment->getPayment()->getNotVoid();
    }
}
