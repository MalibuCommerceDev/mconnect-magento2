<?php

namespace MalibuCommerce\MConnect\Model;

class SimpleXMLExtended extends SimpleXMLElement
{
    public function addCData($value)
    {
        $node = dom_import_simplexml($this);
        $no = $node->ownerDocument;
        $node->appendChild($no->createCDATASection($value));
    }
}
