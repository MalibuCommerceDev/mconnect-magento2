<?php

namespace MalibuCommerce\MConnect\Helper;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Mail extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var \Magento\Email\Model\TemplateFactory
     */
    protected $emailTemplateFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\App\Helper\Context
     */
    protected $context;

    /**
     * @var \Magento\Framework\Translate\Inline\StateInterface
     */
    protected $inlineTranslation;

    /**
     * @var \MalibuCommerce\MConnect\Model\Config
     */
    protected $config;

    /**
     * @var \Magento\User\Helper\Data
     */
    protected $customerHelper;

    /**
     * Mail constructor.
     *
     * @param \Magento\Framework\Mail\Template\TransportBuilder  $transportBuilder
     * @param \Magento\Email\Model\TemplateFactory               $emailTemplateFactory
     * @param \Magento\Store\Model\StoreManagerInterface         $storeManager
     * @param \Magento\Framework\App\Helper\Context              $context
     * @param \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation
     * @param \MalibuCommerce\MConnect\Model\Config              $config
     * @param \Magento\User\Helper\Data                          $customerHelper
     */
    public function __construct(
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Email\Model\TemplateFactory $emailTemplateFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \MalibuCommerce\MConnect\Model\Config $config,
        \Magento\User\Helper\Data $customerHelper
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->emailTemplateFactory = $emailTemplateFactory;
        $this->storeManager = $storeManager;
        $this->context = $context;
        $this->inlineTranslation = $inlineTranslation;
        $this->config = $config;
        $this->customerHelper = $customerHelper;

        parent::__construct($context);
    }

    /**
     * @return \MalibuCommerce\MConnect\Model\Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param array $variables
     *
     * @return bool|null
     */
    public function sendErrorEmail(array $variables)
    {
        if (!$this->config->isErrorEmailingEnabled()) {
            return null;
        }

        try {
            return $this->sendTemplateEmail(
                null,
                null,
                $this->config->getErrorEmailTemplate(),
                $this->config->getErrorEmailSender(),
                $variables,
                $this->config->getErrorRecipients()
            );
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param \Magento\Customer\Model\Customer $customer
     *
     * @return bool|null
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\MailException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function resetPasswordForNewCustomer($customer)
    {
        if (!$this->config->isNewCustomerPasswordResetEmailingEnabled()) {
            return null;
        }

        if ($customer->getId()) {
            $newPassResetToken = $this->customerHelper->generateResetPasswordLinkToken();
            $customer->changeResetPasswordLinkToken($newPassResetToken)
                ->setSkipMconnect(true);
            $customer->save();

            $storeId = $customer->getStoreId();
            if (!$storeId) {
                $storeId = $this->getCustomerWebsiteStoreId($customer);
            }

            return $this->sendTemplateEmail(
                $customer->getEmail(),
                $customer->getName(),
                $this->config->getNewCustomerPasswordResetEmailTemplate(),
                $this->config->getNewCustomerPasswordResetEmailSender(),
                ['customer' => $customer, 'store' => $this->storeManager->getStore($storeId)],
                [],
                [],
                \Magento\Framework\App\Area::AREA_FRONTEND,
                $storeId
            );
        }

        return false;
    }

    /**
     * Get either first store ID from a set website or the provided as default
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @param int|string|null                  $defaultStoreId
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getCustomerWebsiteStoreId($customer, $defaultStoreId = null)
    {
        if ($customer->getWebsiteId() != 0 && empty($defaultStoreId)) {
            $storeIds = $this->storeManager->getWebsite($customer->getWebsiteId())->getStoreIds();
            $defaultStoreId = reset($storeIds);
        }

        return $defaultStoreId;
    }

    /**
     * Send transactional/template email
     *
     * @param string     $mainEmail
     * @param string     $mainEmailName
     * @param string|int $template
     * @param string|int $sender
     * @param array      $variables
     * @param array      $emailToList
     * @param array      $bccEmailToList
     * @param string     $area
     * @param int        $storeId
     *
     * @return bool
     *
     * @throws \Magento\Framework\Exception\MailException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function sendTemplateEmail(
        $mainEmail,
        $mainEmailName,
        $template,
        $sender,
        $variables = array(),
        $emailToList = array(),
        $bccEmailToList = array(),
        $area = \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE,
        $storeId = \Magento\Store\Model\Store::DEFAULT_STORE_ID
    ) {
        if (!$sender || (!$mainEmail && !$emailToList) || !$template || $template == 'none') {
            throw new \LogicException(
                __('Template "%1" Email was was not sent. Required data is missing.', $template)
            );
        }

        $emailToList = $this->filterEmails($emailToList);
        $bccEmailToList = $this->filterEmails($bccEmailToList);

        if (empty($mainEmail) && !empty($emailToList)) {
            $mainEmail = array_shift($emailToList);
        }
        if (empty($mainEmail)) {
            throw new \LogicException(
                __('Template "%1" Email was was not sent. Email to address is missing.', $template)
            );
        }

        $storeId = $storeId ? $storeId : $this->storeManager->getStore()->getId();

        $this->inlineTranslation->suspend();

        $this->transportBuilder
            ->setTemplateIdentifier($template)
            ->setTemplateOptions(['area' => $area, 'store' => $storeId])
            ->setTemplateVars($variables)
            ->setFrom($sender)
            ->addTo($mainEmail, $mainEmailName)
            ->setReplyTo($mainEmail);

        if (count($bccEmailToList) > 0) {
            foreach ($bccEmailToList as $email) {
                $this->transportBuilder->addBcc($email);
            }
        }

        $this->transportBuilder->getTransport()
            ->sendMessage();

        if (count($emailToList) > 0) {
            foreach ($emailToList as $email) {
                $this->transportBuilder
                    ->setTemplateIdentifier($template)
                    ->setTemplateOptions(['area' => $area, 'store' => $storeId])
                    ->setTemplateVars($variables)
                    ->setFrom($sender)
                    ->addTo($email)
                    ->setReplyTo($email)
                    ->getTransport()
                    ->sendMessage();
            }
        }

        $this->inlineTranslation->resume();

        return true;
    }

    /**
     * Sanitize, remove duplicates, remove invalid emails
     *
     * @param string|array $emails
     *
     * @return array
     */
    protected function filterEmails($emails)
    {
        if (!is_array($emails)) {
            $emails = trim($emails, " \t\n\r\0\x0B,");
            $emails = explode(',', $emails);
        }

        if (empty($emails)) {
            return array();
        }

        $emails = array_map(function ($email) {
            return trim($email, " \t\n\r\0\x0B,");
        }, $emails);

        $emails = array_filter($emails, function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        return array_unique($emails);
    }
}
