<?php

namespace MalibuCommerce\MConnect\Helper;

use \Magento\Framework\App\Filesystem\DirectoryList;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    public function getLogFile($id, $absolute = true, $nameOnly = false)
    {
        $directoryList = new DirectoryList(BP);

        $dir = 'mconnect';
        if ($id) {
            $file = 'queue_' . $id . '.log';
        } else {
            $file = 'navision_soap.log';
        }
        $logDirObj = $directoryList;
        $logDir = $logDirObj->getPath('log');
        $logDir .= DIRECTORY_SEPARATOR . $dir;
        if (!is_dir($logDir)) {
            mkdir($logDir, 0770, true);
        }

        $file = ($absolute ? $logDir : $dir) . DIRECTORY_SEPARATOR . $file;
        return !file_exists($file) && !$nameOnly ? false : $file;
    }

    public function getFileSize($file)
    {
        if (!file_exists($file)) {
            return false;
        }
        $bytes = filesize($file);
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return number_format($bytes, 2) . ' ' . $units[$pow];
    }

    public function getLogFileContents($queueId, $asString = true)
    {
        if ($file = $this->getLogFile($queueId, true, true)) {
            $contents = file_get_contents($file);
            $results = [];
            if (preg_match_all('~({.+})~', $contents, $matches)) {
                foreach ($matches[1] as $match) {
                    $debug = json_decode($match);
                    $result = [];
                    foreach ($debug as $title => $data) {
                        if (preg_match('~({.+})~', $data, $matches2)) {
                            $data = json_decode($matches2[1]);
                        }
                        $result[$title] = $data;
                    }
                    $results[] = $result;
                }
            }

            if (count($results)) {
              return $asString ? print_r($results, true) : $results;
            }

            return $contents;
        }

        return false;
    }

    /**
     * Generate Queue Item Status as html
     *
     * @param \MalibuCommerce\MConnect\Model\Queue $queueItem
     *
     * @return string
     */
    public function getQueueItemStatusHtml(\MalibuCommerce\MConnect\Model\Queue $queueItem)
    {
        $result = '';
        $status = $queueItem->getStatus();
        $style = 'text-transform: uppercase;'
                 . ' font-weight: bold;'
                 . ' color: white;'
                 . ' font-size: 10px;'
                 . ' width: 100%;'
                 . ' display: block;'
                 . ' text-align: center;'
                 . ' border-radius: 10px;';
        $title = htmlentities($queueItem->getMessage());
        $background = false;
        switch ($status) {
            case \MalibuCommerce\MConnect\Model\Queue::STATUS_PENDING:
                $background = '#9a9a9a';
                break;
            case \MalibuCommerce\MConnect\Model\Queue::STATUS_RUNNING:
                $background = '#28dade';
                break;
            case \MalibuCommerce\MConnect\Model\Queue::STATUS_SUCCESS:
                $background = '#00c500';
                break;
            case \MalibuCommerce\MConnect\Model\Queue::STATUS_ERROR:
                $background = '#ff0000';
                break;
            default:
                $result = $status;
        }
        if ($background) {
            $result = '<span title="' . $title . '" style="' . $style . ' background: ' . $background . ';">' . $status . '</span>';
        }

        return $result;
    }
}
