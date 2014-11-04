# EBANX osCommerce Payment Gateway Extension

This plugin allows you to integrate your osCommerce store with the EBANX payment gateway.
It includes support to installments and custom interest rates.

## Requirements

* PHP >= 5.4
* cURL
* osCommerce >= v2.3

## Installation
### Source
1. Clone the git repo to your osCommerce root folder
```
git clone --recursive https://github.com/ebanx/ebanx-oscommerce.git
```
2. Visit your osCommerce payment methods settings at **Modules > Payment**.
3. Click on the button called "+ Install Modules".
4. If you wish to accept credit card payments from Brazil through EBANX, locate module **EBANX** and click "Install".
5. Click on "Edit". Add the integration key you were given by the EBANX integration team. You will need to use different keys in test and production modes.
6. Change the other settings if needed. Click on "Save".
7. If you wish to accept "Boleto Bancário" and "TEF" payment, click on the "+ Install Modules" again and select module **EBANX Checkout** .
8. Click "Install".
9. If needed, click on "Edit" and add the integration key you were given by the EBANX integration team. You will need to use different keys in test and production modes.
9. Change the other settings if needed. Click on "Save".
10. Go to the EBANX Merchant Area, then to **Integration > Merchant Options**.
  1. Change the _Status Change Notification URL_ to:
```
{YOUR_SITE}/ebanx_notification.php
```
  2. Change the _Response URL_ to:
```
{YOUR_SITE}/ebanx_return.php
```
11. That's all!

## Changelog
* 1.0.2: fixed dob workaround and notification file
* 1.0.0: first release.
