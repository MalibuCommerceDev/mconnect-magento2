<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\Pricerule;

class Edit extends \MalibuCommerce\MConnect\Controller\Adminhtml\Pricerule\PriceuleAction
{
    /**
     * Edit Mconnect Price Rule
     *
     * @return void
     */
    public function execute()
    {
        $this->initAction();

        $model = $this->initRule();
        if ($model->getId()) {
            $breadcrumbTitle = __('Edit Mconnect Price Rule #' . $model->getId());
            $breadcrumbLabel = $breadcrumbTitle;
        } else {
            $breadcrumbTitle = __('New Mconnect Price Rule');
            $breadcrumbLabel = __('Create Mconnect Price Rule');
        }
        $this->_view->getPage()->getConfig()->getTitle()->prepend(__('Mconnect Price Rules'));
        $this->_view->getPage()->getConfig()->getTitle()->prepend($breadcrumbTitle);

        $this->_addBreadcrumb($breadcrumbLabel, $breadcrumbTitle);

        // restore data
        $values = $this->_getSession()->getData('mconnect_rule_form_data', true);
        if ($values) {
            $model->addData($values);
        }

        $this->_view->renderLayout();
    }
}