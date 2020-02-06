<?php

namespace MalibuCommerce\MConnect\Model\Navision;

class Pricerule extends AbstractModel
{
    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {
        $max = $this->config->get(\MalibuCommerce\MConnect\Model\Queue\Pricerule::CODE . '/max_rows');
        $parameters = [
            'skip'     => $page * $max,
            'max_rows' => $max,
        ];
        if ($lastUpdated) {
            $parameters['last_updated'] = $lastUpdated;
        }

        return $this->_export('sales_price_export', $parameters, $websiteId);
    }
}
