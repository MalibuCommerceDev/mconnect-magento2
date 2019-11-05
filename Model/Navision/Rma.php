<?php

namespace MalibuCommerce\MConnect\Model\Navision;


class Rma extends AbstractModel
{
    /**
     * @param int  $page
     * @param bool $lastUpdated
     * @param int  $websiteId
     *
     * @return \simpleXMLElement
     * @throws \Throwable
     */
    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {
        $max = $this->config->get(\MalibuCommerce\MConnect\Model\Queue\Rma::CODE . '/max_rows');
        $parameters = array(
            'skip'     => $page * $max,
            'max_rows' => $max,
        );
        if ($lastUpdated) {
            $parameters['last_updated'] = $lastUpdated;
        }

        return $this->_export('return_receipt_export', $parameters, $websiteId);
    }
}