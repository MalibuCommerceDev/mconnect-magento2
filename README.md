Malibu Connect for Magento 2
===

# Installation

## Request access to Malibu Connect module Bitbucket repository

## Install Module via composer

```
$ composer config repositories.mconnect vcs git@bitbucket.org:MalibuCommerceDev/mconnect-magento2.git
$ composer require --update-no-dev malibucommerce/mconnect-magento2:~2.6.4
$ php bin/magento module:enable MalibuCommerce_MConnect
$ php bin/magento setup:upgrade
$ php bin/magento setup:di:compile
$ php bin/magento setup:static-content:deploy --jobs 1 -f
```