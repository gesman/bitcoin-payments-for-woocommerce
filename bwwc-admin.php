<?php
/*
Bitcoin Payments for WooCommerce
http://www.bitcoinway.com/
*/

// Include everything
include (dirname(__FILE__) . '/bwwc-include-all.php');

//===========================================================================
// Global vars.

global $g_BWWC__plugin_directory_url;
$g_BWWC__plugin_directory_url = plugins_url ('' , __FILE__);
//===========================================================================

//===========================================================================
// Global default settings
global $g_BWWC__config_defaults;
$g_BWWC__config_defaults = array (

   // ------- Hidden constants
   'supported-currencies-arr'       => array ('USD', 'AUD', 'CAD', 'CHF', 'CNY', 'DKK', 'EUR', 'GBP', 'HKD', 'JPY', 'NZD', 'PLN', 'RUB', 'SEK', 'SGD', 'THB'),

   // ------- General Settings
   'license-key'                    => 'UNLICENSED',
   'api-key'                        => '0000',
   );
//===========================================================================

//===========================================================================
function BWWC__GetPluginNameVersionEdition()
{
   return '<h2 style="border-bottom:1px solid #DDD;padding-bottom:10px;margin-bottom:20px;">' .
            BWWC_PLUGIN_NAME . ', version: <span style="color:#EE0000;">' .
            BWWC_VERSION. '</span> [<span style="color:#EE0000;background-color:#FFFF77;">&nbsp;' .
            BWWC_EDITION . '&nbsp;</span> edition]' .
          '</h2>';
}
//===========================================================================

//===========================================================================
function BWWC__get_settings ($key=false)
{
   global   $g_BWWC__plugin_directory_url;
   global   $g_BWWC__config_defaults;

   $bwwc_settings = get_option (BWWC_SETTINGS_NAME);



   if (!$key)
      return ($bwwc_settings);
   else
      return (@$bwwc_settings[$key]);
}
//===========================================================================

//===========================================================================
function BWWC__update_settings ($bwwc_use_these_settings="")
{
   if ($bwwc_use_these_settings)
      {
      update_option (BWWC_SETTINGS_NAME, $bwwc_use_these_settings);
      return;
      }

   global   $g_BWWC__config_defaults;

   // Load current settings and overwrite them with whatever values are present on submitted form
   $bwwc_settings = BWWC__get_settings();

   foreach ($g_BWWC__config_defaults as $k=>$v)
      {
      if (isset($_POST[$k]))
         {
         if (!isset($bwwc_settings[$k]))
            $bwwc_settings[$k] = ""; // Force set to something.
         BWWC__update_individual_bwwc_setting ($bwwc_settings[$k], $_POST[$k]);
         }
      // If not in POST - existing will be used.
      }

   //---------------------------------------
   // Validation
   //if ($bwwc_settings['aff_payout_percents3'] > 90)
   //   $bwwc_settings['aff_payout_percents3'] = "90";
   //---------------------------------------

   update_option (BWWC_SETTINGS_NAME, $bwwc_settings);
}
//===========================================================================

//===========================================================================
// Takes care of recursive updating
function BWWC__update_individual_bwwc_setting (&$bwwc_current_setting, $bwwc_new_setting)
{
   if (is_string($bwwc_new_setting))
      $bwwc_current_setting = BWWC__stripslashes ($bwwc_new_setting);
   else if (is_array($bwwc_new_setting))  // Note: new setting may not exist yet in current setting: curr[t5] - not set yet, while new[t5] set.
      {
      // Need to do recursive
      foreach ($bwwc_new_setting as $k=>$v)
         {
         if (!isset($bwwc_current_setting[$k]))
            $bwwc_current_setting[$k] = "";   // If not set yet - force set it to something.
         BWWC__update_individual_bwwc_setting ($bwwc_current_setting[$k], $v);
         }
      }
   else
      $bwwc_current_setting = $bwwc_new_setting;
}
//===========================================================================

//===========================================================================
//
// Reset settings only for one screen
function BWWC__reset_partial_settings ()
{
   global   $g_BWWC__config_defaults;

   // Load current settings and overwrite ones that are present on submitted form with defaults
   $bwwc_settings = BWWC__get_settings();

   foreach ($_POST as $k=>$v)
      {
      if (isset($g_BWWC__config_defaults[$k]))
         {
         if (!isset($bwwc_settings[$k]))
            $bwwc_settings[$k] = ""; // Force set to something.
         BWWC__update_individual_bwwc_setting ($bwwc_settings[$k], $g_BWWC__config_defaults[$k]);
         }
      }

   update_option (BWWC_SETTINGS_NAME, $bwwc_settings);
}
//===========================================================================

//===========================================================================
function BWWC__reset_all_settings ()
{
   global   $g_BWWC__config_defaults;

   update_option (BWWC_SETTINGS_NAME, $g_BWWC__config_defaults);

   BWWC__Validate_License ($g_BWWC__config_defaults['license-key']);
}
//===========================================================================

//===========================================================================
// Recursively strip slashes from all elements of multi-nested array
function BWWC__stripslashes (&$val)
{
   if (is_string($val))
      return (stripslashes($val));
   if (!is_array($val))
      return $val;

   foreach ($val as $k=>$v)
      {
      $val[$k] = BWWC__stripslashes ($v);
      }

   return $val;
}
//===========================================================================

//===========================================================================
function BWWC__create_database_tables ($bwwc_settings)
{

}
//===========================================================================

//===========================================================================
// NOTE: Irreversibly deletes all plugin tables and data
function BWWC__delete_database_tables ()
{

}
//===========================================================================

