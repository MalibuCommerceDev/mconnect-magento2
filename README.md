MConnect for Magento 2
===

# Installation

Install via composer

```
composer config repositories.mconnect vcs git@bitbucket.org:MalibuCommerceDev/mconnect-magento2.git
composer require malibucommerce/mconnect-magento2:dev-master
```

Enabled the module

```
php bin/magento module:enable MalibuCommerce_MConnect
```

Upgrade

```
php bin/magento setup:upgrade
```

And re-compile

```
php bin/magento setup:di:compile
```