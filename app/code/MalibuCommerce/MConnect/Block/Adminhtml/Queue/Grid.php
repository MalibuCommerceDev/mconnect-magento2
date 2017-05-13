<?php
namespace MalibuCommerce\MConnect\Block\Adminhtml\Queue;


class Grid extends \Magento\Backend\Block\Widget\Grid
{

    /**
     * @var \MalibuCommerce\MConnect\Model\Resource\Queue\Collection
     */
    protected $mConnectResourceQueueCollection;

    /**
     * @var \MalibuCommerce\MConnect\Model\Queue
     */
    protected $mConnectQueue;

    /**
     * @var \Magento\Customer\Model\Customer
     */
    protected $customerCustomer;

    /**
     * @var \Magento\Catalog\Model\Product
     */
    protected $catalogProduct;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Resource\Queue\Collection $mConnectResourceQueueCollection,
        \MalibuCommerce\MConnect\Model\Queue $mConnectQueue,
        \Magento\Customer\Model\Customer $customerCustomer,
        \Magento\Catalog\Model\Product $catalogProduct,
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Helper\Data $backendHelper
    )
    {
        $this->mConnectResourceQueueCollection = $mConnectResourceQueueCollection;
        $this->mConnectQueue = $mConnectQueue;
        $this->customerCustomer = $customerCustomer;
        $this->catalogProduct = $catalogProduct;
        parent::__construct($context, $backendHelper);
        $this->setId('queueGrid');
        $this->setDefaultSort('id');
        $this->setDefaultDir(\Zend_Db_Select::SQL_DESC);
        $this->setUseAjax(true);
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        $collection = $this->mConnectResourceQueueCollection;
        $collection->getSelect()
            ->joinLeft(
                array('connection' => $collection->getTable('malibucommerce_mconnect/connection')),
                'main_table.connection_id = connection.id',
                array('connection_name' => 'connection.name')
            )
            ->columns(array('duration' => 'IF(main_table.finished_at, TIME_TO_SEC(TIMEDIFF(main_table.finished_at, main_table.started_at)), "")'))
        ;
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('id', array(
            'header' => __('ID'),
            'align'  =>'right',
            'type'   => 'number',
            'index'  => 'id',
            'width'  => '50px',
        ));
        $this->addColumn('code', array(
            'header' => __('Code'),
            'index'  => 'code',
            'width'  => '100px',
        ));
        $this->addColumn('action', array(
            'header' => __('Action'),
            'index'  => 'action',
            'width'  => '100px',
        ));
        $this->addColumn('entity_id', array(
            'header'         => __('Entity'),
            'index'          => 'entity_id',
            'type'           => 'number',
            'frame_callback' => array($this, 'decorateEntity'),
        ));
        $this->addColumn('details', array(
            'header'         => __('Details'),
            'index'          => 'details',
            'frame_callback' => array($this, 'decorateDetails'),
        ));
        $this->addColumn('created_at', array(
            'header' => __('Created At'),
            'index'  => 'created_at',
            'type'   => 'datetime',
        ));
        $this->addColumn('started_at', array(
            'header' => __('Synced At'),
            'index'  => 'started_at',
            'type'   => 'datetime',
        ));
        $this->addColumn('duration', array(
            'header'         => __('Duration'),
            'index'          => 'duration',
            'filter'         => false,
            'frame_callback' => array($this, 'decorateDuration'),
            'width'          => '50px',
        ));
        $this->addColumn('connection_name', array(
            'header'       => __('Connection'),
            'index'        => 'connection_name',
            'filter_index' => 'connection.name',
        ));
        $this->addColumn('message', array(
            'header'         => __('Messages'),
            'index'          => 'message',
            'frame_callback' => array($this, 'decorateMessage'),
        ));
        $this->addColumn('log', array(
            'header'         => __('Log'),
            'filter'         => false,
            'sortable'       => false,
            'frame_callback' => array($this, 'decorateLog'),
            'width'          => '50px',
        ));
        $this->addColumn('status', array(
            'header'         => __('Status'),
            'index'          => 'status',
            'type'           => 'options',
            'options'        => $this->mConnectQueue->getAllStatuses(),
            'frame_callback' => array($this, 'decorateStatus'),
        ));
        return parent::_prepareColumns();
    }

    public function decorateMessage($value, $row)
    {
        $return = '';
        if (!empty($value)) {
            $return .= '<a href="#" onclick="$(\'messages_' . $row->getId() . '\').toggle(); return false;">' . $this->__('View') . '</a>';
            $return .= '<div id="messages_' . $row->getId() . '" style="display: none; width: 300px; font-size: small;"><pre style="white-space: pre-line; word-break: break-word;">' . $value . '</pre></div>';
        }
        return $return;
    }

    public function decorateLog($value, $row)
    {
        $file = \MalibuCommerce\MConnect\Model\Navision\Connection\Soap\Client::getLogFile($row->getId());
        if (!file_exists($file)) {
            return;
        }
        return '<a href="' . $this->getUrl('*/*/log', array('id' => $row->getId())) . '" target="_blank">' . $this->__('View') . '</a>';
    }

    public function decorateEntity($value, $row)
    {
        if (!$value) {
            return $value;
        }
        $link = false;
        $title = false;
        if ($row->getCode() === 'customer') {
            if ($row->getAction() === 'export') {
                $link = $this->getUrl('*/customer/edit', array('id' => $value));
                $title = $this->customerCustomer->load($value)->getEmail();
            }
        }
        if ($row->getCode() === 'product') {
            if ($row->getAction() === 'import_single') {
                $link = $this->getUrl('*/product/edit', array('id' => $value));
                $title = $this->catalogProduct->load($value)->getName();
            }
        }
        if ($link !== false) {
            return sprintf('<a href="%s" target="_blank" title="%s">%s<a/>', $link, $title ? $title : $value, $value);
        }
        return $value;
    }

    public function decorateStatus($status)
    {
        $style = 'text-transform: uppercase;'
            .' font-weight: bold;'
            .' color: white;'
            .' font-size: 10px;'
            .' width: 100%;'
            .' display: block;'
            .' text-align: center;'
            .' border-radius: 10px;'
        ;
        switch ($status) {
            case MalibuCommerce_Mconnect_Model_Queue::STATUS_PENDING:
                $result = '<span style="' . $style . ' background: #9a9a9a;">' . $status . '</span>';
                break;
            case MalibuCommerce_Mconnect_Model_Queue::STATUS_RUNNING:
                $result = '<span style="' . $style . ' background: #28dade;">' . $status . '</span>';
                break;
            case MalibuCommerce_Mconnect_Model_Queue::STATUS_SUCCESS:
                $result = '<span style="' . $style . ' background: #00c500;">' . $status . '</span>';
                break;
            case MalibuCommerce_Mconnect_Model_Queue::STATUS_ERROR:
                $result = '<span style="' . $style . ' background: #ff0000;">' . $status . '</span>';
                break;
            default:
                $result = $status;
        }
        return $result;
    }

    public function decorateDuration($seconds)
    {
        return sprintf('%02dm:%02ds', floor($seconds / 60), $seconds % 60);
    }

    public function decorateDetails($value)
    {
        $html = '';
        if ($value && $values = json_decode(html_entity_decode($value))) {
            foreach ($values as $key => $value) {
                $html .= sprintf('<strong>%s</strong>: %s<br />', uc_words($key, ' '), $value);
            }
        }
        return $html;
    }

    public function getRowUrl($row)
    {
        return false;
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('id');
        $this->getMassactionBlock()->setFormFieldName('ids')
            ->setUseSelectAll(true)
            ->addItem('queue', array(
                 'label'   => __('Re-Queue'),
                 'url'     => $this->getUrl('*/*/massQueue'),
            ))
            ->addItem('queueAndSync', array(
                 'label'   => __('Re-Queue and Sync Now'),
                 'url'     => $this->getUrl('*/*/massQueueAndSync'),
            ))
            ->addItem('sync', array(
                 'label'   => __('Sync Now'),
                 'url'     => $this->getUrl('*/*/massSync'),
            ));
        return $this;
    }
}
