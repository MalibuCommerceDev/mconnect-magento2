<?php

namespace MalibuCommerce\MConnect\Model\Navision;

class Inventory extends AbstractModel
{
    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {
        $max = $this->config->get(\MalibuCommerce\MConnect\Model\Queue\Inventory::CODE . '/max_rows');
        $parameters = [
            'skip'     => $page * $max,
            'max_rows' => $max,
        ];
        if ($lastUpdated) {
            $parameters['last_updated'] = $lastUpdated;
        }

        $data = '<?xml version="1.0" encoding="utf-8"?>
<inventory_export_resp>
<item_inventory>
<nav_item_id>PSB8Z12-NN</nav_item_id>
<quantity>0</quantity>
<unit_price>6.25</unit_price>
<src2>0</src2>
<src3>110</src3>
</item_inventory>
<status>
<current_date_time>2021-10-27T00:29:26Z</current_date_time>
<record_count>1</record_count>
<end_of_records>true</end_of_records>
</status>
</inventory_export_resp>';

        return new \simpleXMLElement($data);

        return $this->_export('inventory_export', $parameters, $websiteId);
    }
}
