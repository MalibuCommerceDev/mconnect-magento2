<?php
namespace MalibuCommerce\MConnect\Block\Adminhtml\Connection;


class Grid extends \Magento\Backend\Block\Widget\Grid
{

    /**
     * @var \MalibuCommerce\MConnect\Model\Resource\Connection\Collection
     */
    protected $mConnectResourceConnectionCollection;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Helper\Data $backendHelper,
        \MalibuCommerce\MConnect\Model\Resource\Connection\Collection $mConnectResourceConnectionCollection
    )
    {
        $this->mConnectResourceConnectionCollection = $mConnectResourceConnectionCollection;
        parent::__construct($context, $backendHelper);
        $this->setId('connectionGrid');
        $this->setDefaultSort('id');
        $this->setDefaultDir(\Zend_Db_Select::SQL_DESC);
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        $this->setCollection(
            $this->mConnectResourceConnectionCollection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('id', array(
            'header' => __('ID'),
            'align'  =>'right',
            'width'  => '50px',
            'type'   => 'number',
            'index'  => 'id',
        ));
        $this->addColumn('name', array(
            'header' => __('Name'),
            'index'  => 'name',
        ));
        $this->addColumn('url', array(
            'header' => __('URL'),
            'index'  => 'url',
        ));
        $this->addColumn('username', array(
            'header' => __('Username'),
            'index'  => 'username',
        ));
        return parent::_prepareColumns();
    }

    public function getRowUrl($row)
    {
        return $this->getUrl("*/*/edit", array('id' => $row->getId()));
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('id');
        $this->getMassactionBlock()->setFormFieldName('ids')
            ->setUseSelectAll(true)
            ->addItem('delete', array(
                 'label'   => __('Delete'),
                 'url'     => $this->getUrl('*/*/massDelete'),
            ));
        return $this;
    }
}
