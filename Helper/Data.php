<?php
namespace MalibuCommerce\MConnect\Helper;

use \Magento\Framework\App\Filesystem\DirectoryList;

class Data
{

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    /**
     * @var \Magento\Framework\TranslateInterface
     */
    protected $translateInterface;

    /**
     * @var \Magento\Email\Model\Template
     */
    protected $emailTemplate;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \Magento\Framework\TranslateInterface $translateInterface,
        \Magento\Email\Model\Template $emailTemplate,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\User\Helper\Data $userHelper
    ) {
        $this->mConnectConfig = $mConnectConfig;
        $this->translateInterface = $translateInterface;
        $this->emailTemplate = $emailTemplate;
        $this->scopeConfig = $scopeConfig;
        $this->userHelper = $userHelper;
    }
    /**
     * @param array $params
     * @return void
     */
    public function sendErrorEmail(array $params = array())
    {
        $config = $this->mConnectConfig;
        $recipients = $config->getErrorRecipients();
        if (count($recipients) === 0 || empty($recipients[0])) {
            return;
        }
        $translate = $this->translateInterface;
        $translate->setTranslateInline(false);
        $params = array_merge(array(
            'title'    => '',
            'body'     => '',
            'response' => '',
        ), $params);
        foreach ($recipients as $recipient) {
            $emailTemplate = $this->emailTemplate;
            $emailTemplate->setDesignConfig(array('area' => 'backend'));
            $emailTemplate->sendTransactional(
                'malibucommerce_mconnect_navision_error',
                array(
                    'name' => $this->scopeConfig->getValue('trans_email/ident_support/name', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                    'email' => $this->scopeConfig->getValue('trans_email/ident_support/email', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
                ),
                $recipient,
                null,
                $params
            );
        }

        $translate->setTranslateInline(true);
    }

    public function sendNewCustomerEmail($customer)
    {
        $config = $this->mConnectConfig;
        if (!$config->getFlag('customer/new_email_enabled')) {
            return false;
        }
        $translate = $this->translateInterface;
        $translate->setTranslateInline(false);
        $newResetPasswordLinkToken = $this->userHelper->generateResetPasswordLinkToken();
        $customer->changeResetPasswordLinkToken($newResetPasswordLinkToken);
        $emailTemplate = $this->emailTemplate;
        $emailTemplate->setDesignConfig(array('area' => 'backend'));
        $emailTemplate->sendTransactional(
            $config->get('customer/new_email_template'),
            $config->get('customer/new_email_identity'),
            array(
                'name' => $customer->getName(),
                'email' => $customer->getEmail(),
            ),
            null,
            array('customer' => $customer)
        );
        $translate->setTranslateInline(true);
        return true;
    }

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
            mkdir($logDir);
            chmod($logDir, 0750);
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
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function getLogFileContents($queueId, $asString = true)
    {
        if ($file = $this->getLogFile($queueId, true, true)) {
            $contents = file_get_contents($file);
            $result = [];
            $matches = [];
            if (preg_match('~({.+})~', $contents, $matches)) {
                $debug = json_decode($matches[1]);
                foreach ($debug as $title => $data) {
                    if (preg_match('~({.+})~', $data, $matches2)) {
                        $data = json_decode($matches2[1]);
                    }
                    $result[$title] = $data;
                }
            }

            if ($result) {
              return $asString ? print_r($result, true) : $result;
            } else {
                return $contents;
            }
        }
        return false;
    }
}
