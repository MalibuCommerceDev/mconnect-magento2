<?php

namespace MalibuCommerce\MConnect\Block\Navision;

/**
 * @method setInvoices(array $invoices) Invoice
 * @method getInvoices() array
 */
class Invoice extends OrderHistory
{
    /**
     * @var string
     */
    protected $_template = 'navision/invoices.phtml';
}
