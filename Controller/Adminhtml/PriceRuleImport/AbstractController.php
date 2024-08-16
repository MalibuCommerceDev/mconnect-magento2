<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\PriceRuleImport;

use Magento\Backend\App\Action;

abstract class AbstractController extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'MalibuCommerce_MConnect::price_rule_import';
}
