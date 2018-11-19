<?php

namespace MalibuCommerce\MConnect\Model\Navision;

class Inventory extends \MalibuCommerce\MConnect\Model\Navision\AbstractModel
{
    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {
        $max = $this->config->get('inventory/max_rows');
        $parameters = array(
            'skip'     => $page * $max,
            'max_rows' => $max,
        );
        if ($lastUpdated) {
            $parameters['last_updated'] = $lastUpdated;
        }

        return $this->_export('inventory_export', $parameters, $websiteId);
    }

}