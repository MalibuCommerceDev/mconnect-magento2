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
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param Context                               $context
     * @param JsonFactory                           $resultJsonFactory
     * @param \MalibuCommerce\MConnect\Model\Config $mConnectConfig
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        \MalibuCommerce\MConnect\Model\Config $mConnectConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->mConnectConfig = $mConnectConfig;
        $this->storeManager = $storeManager;
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
            $websiteId = $this->getRequest()->getParam('website', 0);
            $storeId = $this->getRequest()->getParam('store', 0);
            if ($storeId) {
                $websiteId = $this->storeManager->getStore($storeId)->getId();
            }
            if (empty($websiteId)) {
                $websiteId = null;
            }
            $url = $this->mConnectConfig->getNavConnectionUrl($websiteId);
            $username = $this->mConnectConfig->getNavConnectionUsername($websiteId);
            $password = $this->mConnectConfig->getNavConnectionPassword($websiteId);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_URL            => $url,
                CURLOPT_USERAGENT      => 'PHP-SOAP-CURL',
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_USERPWD        => $username . ':' . $password,
                CURLOPT_TIMEOUT        => $this->mConnectConfig->getConnectionTimeout($websiteId),
                CURLOPT_HEADER         => true,
                CURLOPT_NOBODY         => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            ];
            if ($this->mConnectConfig->getIsInsecureConnectionAllowed($websiteId)) {
                $options[CURLOPT_SSL_VERIFYHOST] = 0;
                $options[CURLOPT_SSL_VERIFYPEER] = 0;
            }
            if ($method = $this->mConnectConfig->getAuthenticationMethod($websiteId)) {
                $options[CURLOPT_HTTPAUTH] = $method;
            }
            curl_setopt_array($ch, $options);
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $response = curl_error($ch);
            }
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $success = $httpCode === 200;
        } catch (\Throwable $e) {
            $response = $e->getMessage();
        }
        curl_close($ch);

        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultJsonFactory->create();

        return $result->setData([
            'success'  => $success,
            'time'     => number_format(microtime(true) - $time, 2, '.', ''),
            'response' => $response,
        ]);
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('MalibuCommerce_MConnect::config');
    }
}
