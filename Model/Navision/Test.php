<?php
namespace MalibuCommerce\MConnect\Model\Navision;


class Test extends \MalibuCommerce\MConnect\Model\Navision\AbstractModel
{
    public function testConnection()
    {
        try {
            $this->getConnection()->ExportTaxGroups(array('ztaxGroups' => 1));
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
