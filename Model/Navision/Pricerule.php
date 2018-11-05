<?php

namespace MalibuCommerce\MConnect\Model\Navision;

class Pricerule extends \MalibuCommerce\MConnect\Model\Navision\AbstractModel
{
    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {
        $max = $this->config->getWebsiteData('price_rule/max_rows', $websiteId);
        $parameters = array(
            'skip'     => $page * $max,
            'max_rows' => $max,
        );
        if ($lastUpdated) {
            $parameters['last_updated'] = $lastUpdated;
        }

        return $this->_export('sales_price_export', $parameters, $websiteId);
    }
}