=== Bitcoin Cash Payments for WooCommerce ===
Contributors: mboyd1, gesman, bitcoinway.com
Donation address (bitcoin cash only): 18vzABPyVbbia8TDCKDtXJYXcoAFAPk2cj
Tags: bitcoin cash, bitcoin cash wordpress plugin, bitcoin cash plugin, bitcoin cash payments, accept bitcoin cash, bch, bcc
Requires at least: Wordpress 3.0.1
Tested up to: Wordpress 4.8.1
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html


Bitcoin Cash Payments for WooCommerce is a Wordpress plugin that allows you to accept bitcoin cash at WooCommerce-powered online stores.

== Description ==

Your online store must use WooCommerce platform (free wordpress plugin).
Once you installed and activated WooCommerce, you may install and activate Bitcoin Cash Payments for WooCommerce.

= Benefits =

* Accept payment directly into your personal Electron Cash wallet.
* Electron Cash wallet payment option completely removes dependency on any third party service and middlemen.
* Accept payment in bitcoin cash for physical and digital downloadable products.
* Add bitcoin cash  payments option to your existing online store with alternative main currency.
* Flexible exchange rate calculations fully managed via administrative settings.
* Zero fees and no commissions for bitcoin cash payments processing from any third party.
* Set main currency of your store to USD or bitcoin cash.
* Automatic conversion to bitcoin cash via realtime exchange rate feed and calculations.
* Ability to set exchange rate calculation multiplier to compensate for any possible losses due to bank conversions and funds transfer fees.


== Installation ==

1. Clone the git repo or download the zip and extract.  Move 'bitcoin-cash-payments-for-woocommerce' dir to /wp-content/plugins/
2. Install "Bitcoin Cash Payments for WooCommerce" plugin just like any other Wordpress plugin.
3. Activate.


== Screenshots ==

1. Checkout with option for bitcoin cash payment.
2. Order received screen, including QR code of bitcoin cash address and payment amount.
3. Bitcoin Cash Gateway settings screen.


== Remove plugin ==

1. Deactivate plugin through the 'Plugins' menu in WordPress
2. Delete plugin through the 'Plugins' menu in WordPress


== Supporters ==

* mboyd1:  https://github.com/mboyd1
* Yifu Guo: http://bitsyn.com/
* Bitcoin Grants: http://bitcoingrant.org/
* Chris Savery: https://github.com/bkkcoins/misc
* lowcostego: http://wordpress.org/support/profile/lowcostego
* WebDesZ: http://wordpress.org/support/profile/webdesz
* ninjastik: http://wordpress.org/support/profile/ninjastik
* timbowhite: https://github.com/timbowhite
* devlinfox: http://wordpress.org/support/profile/devlinfox


== Changelog ==

= 3.03 =
* Forked original bitcoin payment plugin, modified for Bitcoin Cash.  Supports Electron Cash wallet's Master Public Key

= 3.02 =
* Upgraded to support WooCommerce 2.1+
* Upgraded to support Wordpress 3.9
* Fixed bug in cron forcing excessive generation of new bitcoin addresses.
* Fixed bug disallowing finding of new bitcoin addresses to use for orders.
* Fixed buggy SQL query causing issues with delayed order processing even when desired number of confirmations achieved.
* Added support for many more currencies.
* Corrected bitcoin exchange rate calculation using: bitcoinaverage.com, bitcoincharts.com and bitpay.com
* MtGox APIs, services and references completely eliminated from consideration.

= 2.12 =
* Added 'bitcoins_refunded' field to order to input refunded value for tracking.

= 2.11 =
* Minor upgrade - screenshots fix.

= 2.10 =
* Added support for much faster GMP math library to generate bitcoin addresses. This improves performance of checkout 3x - 4x times.
  Special thanks to Chris Savery: https://github.com/bkkcoins/misc
* Improved compatibility with older versions of PHP now allowing to use plugin in wider range of hosting services.

= 2.04 =
* Improved upgradeability from older versions.

= 2.02 =
* Added full support for Electrum Wallet's Master Public Key - the math algorithms allowing for the most reliable, anonymous and secure way to accept online payments in bitcoins.
* Improved overall speed and responsiveness due to multilevel caching logic.

= 1.28 =
* Added QR code image to Bitcoin checkout screen and email.
  Credits: WebDesZ: http://wordpress.org/support/profile/webdesz

= 1.27 =
* Fixed: very slow loading due to MtGox exchange rate API issues.

= 1.26 =
* Fixed PHP warnings for repeated 'define's within bwwc-include-all.php

= 1.25 =
* Implemented security check (secret_key validation logic) to prevent spoofed IPN requests.

= 1.24 =
* Fixed IPN callback notification invocation specific to WC 2.x

= 1.23 =
* Fixed incoming IP check logic for IPN (payment notification) requests.

= 1.22 =
* Fixed inability to save settings bug.
* Added compatibility with both WooCommmerce 1.x and 2.x

== Upgrade Notice ==

soon

== Frequently Asked Questions ==

soon
