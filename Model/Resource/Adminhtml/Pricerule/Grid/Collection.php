<?php

namespace MalibuCommerce\MConnect\Model\Resource\Adminhtml\Pricerule\Grid;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection as GridCollection;
use Magento\Framework\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\Document;
use MalibuCommerce\MConnect\Model\Resource\Pricerule;
use Magento\Framework\Api\SearchCriteriaInterface;

class Collection extends GridCollection implements SearchResultInterface
{
    protected $aggregations;
    protected $_idFieldName = 'id';
    protected $_eventPrefix = 'mconnect_pricerule_pricerule';
    protected $_eventObject = 'pricerule_collection';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(Document::class, Pricerule::class);
    }

    public function getAggregations()
    {
        return $this->aggregations;
    }

    public function setAggregations($aggregations)
    {
        $this->aggregations = $aggregations;
    }

    public function getAllIds($limit = null, $offset = null)
    {
        return $this->getConnection()->fetchCol($this->_getAllIdsSelect($limit, $offset), $this->_bindParams);
    }

    public function getSearchCriteria()
    {
        return null;
    }

    public function setSearchCriteria(SearchCriteriaInterface $searchCriteria = null)
    {
        return $this;
    }

    public function getTotalCount()
    {
        return $this->getSize();
    }

    public function setTotalCount($totalCount)
    {
        return $this;
    }

    public function setItems(array $items = null)
    {
        return $this;
    }
}