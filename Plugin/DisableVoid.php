<?php

declare(strict_types=1);

namespace MalibuCommerce\MConnect\Plugin;

use PayPal\Braintree\Gateway\Config\CanVoidHandler;
use PayPal\Braintree\Gateway\Helper\SubjectReader;

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
     * @param callable $proceed
     * @param array $subject
     * @param int|null $storeId
     *
     * @return bool
     */
    public function aroundHandle(
        CanVoidHandler $pluginSubject,
        callable $proceed,
        array $subject,
        ?int $storeId = null
    ): bool {
        $paymemt = $this->subjectReader->readPayment($subject);
        if (empty($paymemt->getPayment()->getNotVoid())) {
            return $proceed($subject, $storeId);
        }

        return !$paymemt->getPayment()->getNotVoid();
    }
}
