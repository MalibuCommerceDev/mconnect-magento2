<?php

namespace MalibuCommerce\MConnect\Model\Navision\Sales;

class Invoice extends \MalibuCommerce\MConnect\Model\Navision\Export\Common
{
    protected $_rootNode = 'sales_invoice_list';
    protected $_listNode = 'invoice';
}
