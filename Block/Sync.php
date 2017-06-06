<?php
namespace MalibuCommerce\MConnect\Block;


class Sync extends \Magento\Framework\View\Element\Template
{

    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        array $data = []
    ) {
        $this->registry = $registry;
        parent::__construct(
            $context,
            $data
        );
    }

    public function getIdentifier($required = true)
    {
        if (!$this->hasIdentifier()) {
            $id = $this->registry->registry('mconnect_sync_identifier');
            if ($id !== null) {
                $this->setIdentifier($id);
            } else {
                if ($required) {
                    throw new \Magento\Framework\Exception\LocalizedException('No identifier specified.');
                }
                $this->setIdentifier(false);
            }
        }
        return parent::getIdentifier();
    }

    protected function _toHtml()
    {
        try {
            return parent::_toHtml();
        } catch (Exception $e) {
            $this->_logger->critical($e);
            return $this->getLayout()->createBlock('malibucommerce_mconnect/sync_exception')->setException($e)->toHtml();
        }
    }
}
