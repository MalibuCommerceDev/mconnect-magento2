<?php

namespace MalibuCommerce\MConnect\Plugin\Framework\View\Layout\Reader\Visibility;

use Magento\Framework\View\Layout\Reader\Visibility\Condition as Subject;
use Magento\Framework\Simplexml\Element;
use MalibuCommerce\MConnect\Framework\View\Layout\HelperCondition;

class ConditionPlugin
{
    /**
     * @param Subject $condition
     * @param Element $element
     * @param         $result
     *
     * @return array
     */
    public function afterParseConditions(Subject $condition, $result, Element $element)
    {
        $configPath = (string)$element->getAttribute('ifconfig');
        if (empty($configPath)) {
            return $result;
        }

        if (preg_match(HelperCondition::REGEX_ACTION_HELPER, $configPath)) {
            $result['ifconfig'] = [
                'name' => HelperCondition::class,
                'arguments' => [
                    'configPath' => $configPath,
                ],
            ];
        }

        return $result;
    }
}
