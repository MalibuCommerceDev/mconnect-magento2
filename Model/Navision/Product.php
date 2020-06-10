<?php

namespace MalibuCommerce\MConnect\Model\Navision;

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
        $max = $this->config->get(\MalibuCommerce\MConnect\Model\Queue\Product::CODE . '/max_rows');
        $parameters = [
            'skip'     => $page * $max,
            'max_rows' => $max,
        ];
        if ($lastUpdated) {
            $parameters['last_updated'] = $lastUpdated;
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
