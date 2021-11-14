<?php

namespace MalibuCommerce\MConnect\Model;

use MalibuCommerce\MConnect\Model\Logger\Handler;

class Logger extends \Monolog\Logger
{
    /**
     * Set queue item id to change log file name
     *
     * @param int $queueItemId
     *
     * @return Logger
     */
    public function setQueueItemId(int $queueItemId): Logger
    {
        foreach ($this->getHandlers() as $handler) {var_dump(get_class($handler));
            if ($handler instanceof Handler) {
                $handler->setQueueItemId($queueItemId);
            }
        }

        return $this;
    }
}
