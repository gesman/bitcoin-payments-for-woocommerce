=== (BETA) Bitcoin SV Payments for WooCommerce ===
Contributors: mboyd1, sanchaz, gesman, bitcoinway.com
Tags: bitcoin sv, bitcoin sv wordpress plugin, bitcoin sv plugin, bitcoin sv payments, accept bitcoin sv, bsv, bchsv
Requires at least: Wordpress 3.0.1
Tested up to: Wordpress 5.0.2
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html


Bitcoin SV Payments for WooCommerce is a Wordpress plugin that allows you to accept Bitcoin SV at WooCommerce-powered online stores.

== Description ==

Your online store must use WooCommerce platform (free wordpress plugin).
Once you installed and activated WooCommerce, you may install and activate Bitcoin SV Payments for WooCommerce.

This is still in Beta, some bugs may be encountered please open an issue.

= Benefits =

* Accept payment directly into your personal ElectrumSV wallet.
* ElectrumSV wallet payment option completely removes dependency on any third party service and middlemen.
* Accept payment in Bitcoin SV for physical and digital downloadable products.
* Add Bitcoin SV  payments option to your existing online store with alternative main currency.
* Flexible exchange rate calculations fully managed via administrative settings.
* Supports multiple currencies, including Bitcoin SV
* Automatic conversion to Bitcoin SV via exchange rate feed and calculations.
* Ability to set exchange rate calculation multiplier to compensate for any possible losses due to bank conversions and funds transfer fees.


== Installation ==

1. Clone the git repo or download the zip and extract.  Move 'bitcoin-sv-payments-for-woocommerce' dir to /wp-content/plugins/
2. Install "Bitcoin SV Payments for WooCommerce" plugin just like any other Wordpress plugin.
3. Activate.
4. Configure plugin with your local ElectrumSV xpub address


== Screenshots ==

1. Checkout with option for Bitcoin SV payment.
2. Order received screen, including QR code of Bitcoin SV address and payment amount.
3. Bitcoin SV Gateway settings screen.


== Remove plugin ==

1. Deactivate plugin through the 'Plugins' menu in WordPress
2. Delete plugin through the 'Plugins' menu in WordPress


== Supporters ==

* mboyd1:  https://bitcoincloud.net
* sanchaz: http://sanchaz.net
* Yifu Guo: http://bitsyn.com/
* Bitcoin Grants: http://bitcoingrant.org/
* Chris Savery: https://github.com/bkkcoins/misc
* lowcostego: http://wordpress.org/support/profile/lowcostego
* WebDesZ: http://wordpress.org/support/profile/webdesz
* ninjastik: http://wordpress.org/support/profile/ninjastik
* timbowhite: https://github.com/timbowhite
* devlinfox: http://wordpress.org/support/profile/devlinfox


== Changelog ==

= 4.20 =
* Bitcoin SV support. Use Weighted Average exchange rate calculation. ElectrumSV wallet is compatible with this plugin. Previous wallet, Electron Cash version 3.3.2 is last compatible version with BSV.

= 4.19 =
* Rebase from sanchaz's fork, minus cashaddr

= 4.18 =
* Made the gateway payment icon selectable. (Adding new ones is possible by uploading it to /images/checkout-icons, make sure to scale the image to a height of 32px). Changed the defaut icon to a new orange icon.

= 4.17 =
* Hardcron behaviour now also happens if soft_cron is set and DISABLE_WP_CRON = true, ie the user is running it manually or through real cron
* The template now features the amount after the address and a message.

= 4.16 =
* Added reuse_expired_addresses option in the menus for everyone

= 4.15 =
* Added exchange rate to order metadata

= 4.14 =
* Changed qr code to cashaddr. Only one qr is displayed to avoid clutter and encourage use of cashaddr.

= 4.13 =
* Added simple casdddr support. This means displays cashaddr on pay page, also adds it to the post metadata for easier search. (Does not use it to query apis, will be added later)
* Fixed styling issues using php cs fixer v2 using PSR2 rules
* Remove donation address and plea

= 4.12 =
* Fixed multiple currency.
* Added new price provider.

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
