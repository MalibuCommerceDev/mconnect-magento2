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
        $helper = (string)$element->getAttribute('ifhelper');
        if (!empty($helper)) {
            $result['ifhelper'] = [
                'name' => HelperCondition::class,
                'arguments' => [
                    'helper' => $helper,
                ],
            ];
        }

        return $result;
    }
}