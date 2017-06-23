MConnect for Magento 2
===

# Installation

Add the repository to your `composer.json`

```
"repositories": [
    {
        "type": "vcs",
        "url": "git@bitbucket.org:MalibuCommerceDev/mconnect-magento2.git"
    }
]
```

And add the requirement to `composer.json`

```
"require": {
    "malibucommerce/mconnect-magento2": "dev-master"
}
```

Download the package

```
composer update
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