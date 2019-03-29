MConnect for Magento 2
===

# Installation

Install via composer

```
$ composer config repositories.mconnect vcs git@bitbucket.org:MalibuCommerceDev/mconnect-magento2.git
$ composer require --update-no-dev malibucommerce/mconnect-magento2:~2.2.2
$ php bin/magento module:enable MalibuCommerce_MConnect
$ php bin/magento setup:upgrade
$ php bin/magento setup:di:compile
```