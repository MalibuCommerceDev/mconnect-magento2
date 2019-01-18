<?php

namespace MalibuCommerce\MConnect\Model\Queue;

interface ImportableEntity
{
    const CODE = 'entity';

    public function getQueueCode();

    public function importAction($websiteId, $navPageNumber);

    public function importEntity(\SimpleXMLElement $data, $websiteId);
}