<?php

namespace MalibuCommerce\MConnect\Framework\View\Layout;

use Magento\Framework\View\Layout\Condition\VisibilityConditionInterface;

/**
 * Check that config flag is set to true,
 */
class HelperCondition implements VisibilityConditionInterface
{
    /**
     * Unique name.
     */
    const NAME = 'ifconfig';

    /**
     * Layout action "ifhelper" param regexp
     */
    const REGEX_ACTION_HELPER = '#^(\\\\[a-z0-9\\\\]+)::([a-z0-9_]+)$#i';

    /**
     * Helper factory
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $helperFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * HelperCondition constructor.
     *
     * @param \Magento\Framework\ObjectManagerInterface  $helperFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $helperFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->helperFactory = $helperFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritdoc
     */
    public function isVisible(array $arguments)
    {
        $run = null;
        if (!preg_match(self::REGEX_ACTION_HELPER, $arguments['configPath'], $run)) {
            throw new \Magento\Framework\Exception\InputException(__('Invalid helper/method definition, expecting "\\className::method".'));
        }

        if (!($helper = $this->helper($run[1])) || !method_exists($helper, $run[2])) {
            throw new \Magento\Framework\Exception\InputException(__('Invalid callback: %1::%2 does not exist', $run[1], $run[2]));
        }
        $callback = array($helper, $run[2]);
        $result = call_user_func($callback, $this->storeManager->getStore());

        return $result;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * Get helper singleton
     *
     * @param string $className
     * @return \Magento\Framework\App\Helper\AbstractHelper
     * @throws \LogicException
     */
    protected function helper($className)
    {
        $helper = $this->helperFactory->get($className);
        if (false === $helper instanceof \Magento\Framework\App\Helper\AbstractHelper) {
            throw new \LogicException($className . ' doesn\'t extend Magento\Framework\App\Helper\AbstractHelper');
        }

        return $helper;
    }
}
