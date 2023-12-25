<?php

namespace MalibuCommerce\MConnect\Model\Navision;

use MalibuCommerce\MConnect\Model\Queue\Product as ProductQueue;

class Product extends AbstractModel
{
    /**
     * Navision export products
     *
     * @param int  $page
     * @param bool $lastUpdated
     * @param int $websiteId
     *
     * @return \simpleXMLElement
     */
    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {
        $max = $this->config->get(ProductQueue::CODE . '/max_rows');
        $parameters = [
            'skip'     => $page * $max,
            'max_rows' => $max,
        ];
        if ($lastUpdated) {
            $parameters['last_updated'] = $lastUpdated;
        }
        if (!empty($this->config->get(ProductQueue::CODE . '/import_enabled_only'))) {
            $parameters['web_enabled'] = true;
        }

        return $this->_export('item_export', $parameters, $websiteId);
    }

    /**
     * Navision export product
     *
     * @param string $navId
     * @param int $websiteId
     *
     * @return \simpleXMLElement
     */
    public function exportSingle($navId, $websiteId = 0)
    {
        return $this->_export('item_export', ['item_nav_id' => $navId], $websiteId);
    }
}
