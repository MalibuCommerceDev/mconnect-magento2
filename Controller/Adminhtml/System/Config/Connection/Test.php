<?php

namespace MalibuCommerce\MConnect\Controller\Adminhtml\System\Config\Connection;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Test extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $mConnectConfig;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param \MalibuCommerce\MConnect\Model\Config $mConnectConfig
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig
    )
    {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->mConnectConfig = $mConnectConfig;
        parent::__construct($context);
    }

    /**
     * Collect relations data
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $success = false;
        $ch = curl_init();
        $time = microtime(true);
        try {
            $url = $this->mConnectConfig->getNavConnectionUrl();
            $username = $this->mConnectConfig->getNavConnectionUsername();
            $password = $this->mConnectConfig->getNavConnectionPassword();

            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_URL => $url,
                CURLOPT_USERAGENT => 'PHP-SOAP-CURL',
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_HTTPAUTH => CURLAUTH_NTLM,
                CURLOPT_USERPWD => $username . ':' . $password,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true,
            ));
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $success = $httpCode === 200;
        } catch (\Exception $e) {
            curl_close($ch);
        }

        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultJsonFactory->create();

        return $result->setData(['success' => $success, 'time' => number_format(microtime(true) - $time, 2, '.', '')]);
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('MalibuCommerce_MConnect::config');
    }
}
