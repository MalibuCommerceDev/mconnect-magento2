<?php

namespace MalibuCommerce\MConnect\Model\Navision;

class Promotion extends AbstractModel
{
    /**
     * @param int  $page
     * @param bool $lastUpdated
     * @param int  $websiteId
     *
     * @return \simpleXMLElement
     */
    public function export($page = 0, $lastUpdated = false, $websiteId = 0)
    {
        $root = new \simpleXMLElement('<promo_export />');
        $root->nav_customer_id = 1;
        $root->mag_customer_id =  2;
        $item = $root->addChild('item');
        $item->addChild('sku', '0100-1000');
        $item1 = $root->addChild('item');
        $item1->addChild('sku', '1936-S');

       /* $root = '<?xml version="1.0" encoding="utf-8"?>
                <promo_export>
                    <nav_customer_id>C1110</nav_customer_id>
                    <mag_customer_id>67868</mag_customer_id>
                    <item>
                        <sku>WT09-XS-Purple</sku>
                        <quantity>2</quantity>
                        <price>10.22</price>
                    </item>
                    <item>
                        <sku>WT09-XS-Black</sku>
                        <quantity>2</quantity>
                        <price>12.22</price>
                    </item>
                </promo_export>';*/
        //$t = $this->_export('promo_export', $root, $websiteId);
        //print_r($t); die;

        $items = '<?xml version="1.0" encoding="utf-8"?>
<promo_export>
    <nav_customer_id>C1110</nav_customer_id>
    <mag_customer_id>67868</mag_customer_id>
    <item>
        <sku>WT09-XS-Purple</sku>
        <quantity>1</quantity>
        <price>1.22</price>
    </item>
    <item>
        <sku>WT09-XS-Black</sku>
        <quantity>1</quantity>
        <price>12.22</price>
    </item>
</promo_export>';

            //['WT09-XS-Purple' => ['price' => 10, 'qty' => 1]];

        return simplexml_load_string($items);
    }

}
