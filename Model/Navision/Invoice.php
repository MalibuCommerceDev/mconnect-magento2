<?php
namespace MalibuCommerce\MConnect\Model\Navision;

class Invoice extends \MalibuCommerce\MConnect\Model\Navision\AbstractModel
{
    public function export($page = 0, $lastUpdated = false)
    {
        $max = $this->config->get('invoice/max_rows');
        $parameters = array(
            'skip'     => $page * $max,
            'max_rows' => $max,
        );
        if ($lastUpdated) {
            $parameters['last_updated'] = $lastUpdated;
        }

        return $this->_export('invoice_export', $parameters);
    }
}