M-Connect for Magento 2
===

# Installation

## Install Module via composer

```
$ composer require malibucommerce/mconnect-magento2
$ php bin/magento module:enable MalibuCommerce_MConnect
$ php bin/magento setup:upgrade
$ php bin/magento setup:di:compile
$ php bin/magento setup:static-content:deploy
```