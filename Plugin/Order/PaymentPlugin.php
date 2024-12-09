<?php

namespace MalibuCommerce\MConnect\Plugin\Order;

use Magento\Sales\Model\Order\Payment;

class PaymentPlugin
{
    /**
     * @param \Magento\Sales\Model\Order $order
     * @param                            $result
     *
     * @return mixed
     */
    public function afterCanVoid(\Magento\Sales\Model\Order\Payment $orderPayment, $result)
    {
        return $result && !$orderPayment->getNotVoid();
    }
}
