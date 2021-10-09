# Magento 2 Customer Account Validation

![Integration Tests](https://github.com/iMi-digital/magento2-customer-activation/actions/workflows/integration.yml/badge.svg)

This module is designed to add the possibility for the site owner to manually validate customer accounts at registration.
This is useful in cases of B2B or private shops.

## Fork

Because the original module is no longer maintained, this module is a hard fork of
[enrico69/magento2-customer-activation](https://github.com/enrico69/magento2-customer-activation/).
We aim to maintain it for php 7.4 compatibility and Magento 2.4+ and add functionality.

## Prerequesites

This module requires php 7.4 and at least Magento 2.4.

## Installation and Update

You can manually download the archive and put its content in the _app/code/IMI/Magento2CustomerActivation_ directory 
or, the simplest (and recommended) way, install it via composer:

```
composer require imi/magento2-customer-activation
```

Whatever method you choosed, activate the module and then run the following command:

```
bin/magento setup:upgrade
bin/magento indexer:reindex
bin/magento cache:clean
```

## Configuration

In the admin panel, got to `Stores > Configuration > Customers > Customer Configuration`.
Open the `Create New Account Options` panel and set _Customer account need to be activated by an admin user_ to true 
for the stores where you want to enable the module.

## How does it work?

After the activation of the module and once you have set the configuration to require account
activation by an admin user, the following process will be followed.

* At the customer registration, the new customer will be logged-out and a message
will notify it that its account is currently waiting for validation.
* The site owner will receive an email notifying them about a new customer waiting for activation.
* Until the account is activated by the admin, customers cannot log in.
* Customers created before the installation are still able to log in and use the site as usual.
* To make an account active, the site owner has to go to the admin panel, edit the 
customer account and set this value to true: _Account is active_.
