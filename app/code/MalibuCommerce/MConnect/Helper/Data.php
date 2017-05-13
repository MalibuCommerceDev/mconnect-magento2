<?php
namespace MalibuCommerce\MConnect\Helper;


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
}
