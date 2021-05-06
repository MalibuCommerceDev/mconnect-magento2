<?php

declare(strict_types=1);

namespace MalibuCommerce\MConnect\Plugin;

use Magento\Braintree\Gateway\Config\CanVoidHandler;
use Magento\Braintree\Gateway\SubjectReader;

class DisableVoid
{
    /**
     * @var SubjectReader
     */
    protected $subjectReader;

    /**
     * @param SubjectReader $subjectReader
     */
    public function __construct(
        SubjectReader $subjectReader
    ) {
        $this->subjectReader = $subjectReader;
    }

    /**
     * Disable void
     *
     * @param CanVoidHandler $pluginSubject
     * @param bool $result
     * @param array $subject
     * @param int|null $storeId
     *
     * @return bool
     */
    public function afterHandle(
        CanVoidHandler $pluginSubject,
        bool $result,
        array $subject,
        ?int $storeId = null
    ): bool {
        $payment = $this->subjectReader->readPayment($subject);

        return $result && !$payment->getPayment()->getNotVoid();
    }
}
