<?php
/*
Bitcoin Payments for WooCommerce
http://www.bitcoinway.com/
*/

//---------------------------------------------------------------------------
// Global definitions
if (!defined('BWWC_PLUGIN_NAME'))
  {
  define('BWWC_VERSION',           '1.27');

  //-----------------------------------------------
  define('BWWC_EDITION',           'Standard');    

  //-----------------------------------------------
  define('BWWC_SETTINGS_NAME',     'BWWC-Settings');
  define('BWWC_PLUGIN_NAME',       'Bitcoin Payments for WooCommerce');   


  // i18n plugin domain for language files
  define('BWWC_I18N_DOMAIN',       'bwwc');
  }
//---------------------------------------------------------------------------





require_once (dirname(__FILE__) . '/bwwc-utils.php');
require_once (dirname(__FILE__) . '/bwwc-admin.php');
require_once (dirname(__FILE__) . '/bwwc-render-settings.php');
require_once (dirname(__FILE__) . '/bwwc-bitcoin-gateway.php');

?>