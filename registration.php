<?php

\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'MalibuCommerce_MConnect',
    isset($file) ? dirname($file) : __DIR__
);
