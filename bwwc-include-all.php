<?php
/*
Bitcoin Payments for WooCommerce
http://www.bitcoinway.com/
*/

//---------------------------------------------------------------------------
// Global definitions
if (!defined('BWWC_PLUGIN_NAME'))
  {
  define('BWWC_VERSION',           '4.11');

  //-----------------------------------------------
  define('BWWC_EDITION',           'Standard');    


  //-----------------------------------------------
  define('BWWC_SETTINGS_NAME',     'BWWC-Settings');
  define('BWWC_PLUGIN_NAME',       'Bitcoin Payments for WooCommerce');   


  // i18n plugin domain for language files
  define('BWWC_I18N_DOMAIN',       'bwwc');

  if (extension_loaded('gmp') && !defined('USE_EXT'))
    define ('USE_EXT', 'GMP');
  else if (extension_loaded('bcmath') && !defined('USE_EXT'))
    define ('USE_EXT', 'BCMATH');
  }
//---------------------------------------------------------------------------

//------------------------------------------
// Load wordpress for POSTback, WebHook and API pages that are called by external services directly.
if (defined('BWWC_MUST_LOAD_WP') && !defined('WP_USE_THEMES') && !defined('ABSPATH'))
   {
   $g_blog_dir = preg_replace ('|(/+[^/]+){4}$|', '', str_replace ('\\', '/', __FILE__)); // For love of the art of regex-ing
   define('WP_USE_THEMES', false);
   require_once ($g_blog_dir . '/wp-blog-header.php');

   // Force-elimination of header 404 for non-wordpress pages.
   header ("HTTP/1.1 200 OK");
   header ("Status: 200 OK");

   require_once ($g_blog_dir . '/wp-admin/includes/admin.php');
   }
//------------------------------------------


// This loads necessary modules and selects best math library
require_once (dirname(__FILE__) . '/libs/util/bcmath_Utils.php');
require_once (dirname(__FILE__) . '/libs/util/gmp_Utils.php');
require_once (dirname(__FILE__) . '/libs/CurveFp.php');
require_once (dirname(__FILE__) . '/libs/Point.php');
require_once (dirname(__FILE__) . '/libs/NumberTheory.php');
require_once (dirname(__FILE__) . '/libs/ElectrumHelper.php');

require_once (dirname(__FILE__) . '/bwwc-cron.php');
require_once (dirname(__FILE__) . '/bwwc-mpkgen.php');
require_once (dirname(__FILE__) . '/bwwc-utils.php');
require_once (dirname(__FILE__) . '/bwwc-admin.php');
require_once (dirname(__FILE__) . '/bwwc-render-settings.php');
require_once (dirname(__FILE__) . '/bwwc-bitcoin-gateway.php');

?>