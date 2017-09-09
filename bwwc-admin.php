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
$g_BWWC__plugin_directory_url = plugins_url ('', __FILE__);

global $g_BWWC__cron_script_url;
$g_BWWC__cron_script_url = $g_BWWC__plugin_directory_url . '/bwwc-cron.php';

//===========================================================================

//===========================================================================
// Global default settings
global $g_BWWC__config_defaults;
$g_BWWC__config_defaults = array (

   // ------- Hidden constants
// 'supported_currencies_arr'             =>  array ('USD', 'AUD', 'CAD', 'CHF', 'CNY', 'DKK', 'EUR', 'GBP', 'HKD', 'JPY', 'NZD', 'PLN', 'RUB', 'SEK', 'SGD', 'THB'), // Not used right now.
   'database_schema_version'              =>  1.4,
   'assigned_address_expires_in_mins'     =>  4*60,   // 4 hours to pay for order and receive necessary number of confirmations.
   'funds_received_value_expires_in_mins' =>  '5',		// 'received_funds_checked_at' is fresh (considered to be a valid value) if it was last checked within 'funds_received_value_expires_in_mins' minutes.
   'starting_index_for_new_btc_addresses' =>  '2',    // Generate new addresses for the wallet starting from this index.
   'max_blockchains_api_failures'         =>  '3',    // Return error after this number of sequential failed attempts to retrieve blockchain data.
   'max_unusable_generated_addresses'     =>  '20',   // Return error after this number of unusable (non-empty) bitcoin addresses were sequentially generated
   'blockchain_api_timeout_secs'          =>  '20',   // Connection and request timeouts for curl operations dealing with blockchain requests.
   'exchange_rate_api_timeout_secs'       =>  '10',   // Connection and request timeouts for curl operations dealing with exchange rate API requests.
   'soft_cron_job_schedule_name'          =>  'minutes_1',   // WP cron job frequency
   'delete_expired_unpaid_orders'         =>  '1',   // Automatically delete expired, unpaid orders from WooCommerce->Orders database
   'reuse_expired_addresses'              =>  '1',   // True - may reduce anonymouty of store customers (someone may click/generate bunch of fake orders to list many addresses that in a future will be used by real customers).
                                                      // False - better anonymouty but may leave many addresses in wallet unused (and hence will require very high 'gap limit') due to many unpaid order clicks.
                                                      //        In this case it is recommended to regenerate new wallet after 'gap limit' reaches 1000.
   'max_unused_addresses_buffer'          =>  10,     // Do not pre-generate more than these number of unused addresses. Pregeneration is done only by hard cron job or manually at plugin settings.
   'cache_exchange_rates_for_minutes'			=>	10,			// Cache exchange rate for that number of minutes without re-calling exchange rate API's.
// 'soft_cron_max_loops_per_run'					=>	2,			// NOT USED. Check up to this number of assigned bitcoin addresses per soft cron run. Each loop involves number of DB queries as well as API query to blockchain - and this may slow down the site.
   'elists'																=>	array(),
   'use_aggregated_api'										=>  '0',		// Use aggregated API to efficiently retrieve bitcoin address balance

   // ------- General Settings
   'license_key'                          =>  'UNLICENSED',
   'api_key'                              =>  substr(md5(microtime()), -16),
   // New, ported from WooCommerce settings pages.
   'service_provider'				 						  =>  'electrum_wallet',		// 'blockchain_info'
   'electrum_mpk_saved'                   =>  '', // Saved, non-normalized value - MPK's separated by space / \n / ,
   'electrum_mpks'                        =>  array(), // Normalized array of MPK's - derived from saved.
   'confs_num'                            =>  '4', // number of confirmations required before accepting payment.
   'exchange_rate_type'                   =>  'vwap', // 'realtime', 'bestrate'.
   'exchange_multiplier'                  =>  '1.00',

   'delete_db_tables_on_uninstall'        =>  '0',
   'autocomplete_paid_orders'							=>  '1',
   'enable_soft_cron_job'                 =>  '1',    // Enable "soft" Wordpress-driven cron jobs.

   // ------- Copy of $this->settings of 'BWWC_Bitcoin' class.
   // DEPRECATED (only blockchain.info related settings still remain there.)
   'gateway_settings'                     =>  array('confirmations' => 6),

   // ------- Special settings
   'exchange_rates'                       =>  array('EUR' => array('method|type' => array('time-last-checked' => 0, 'exchange_rate' => 1), 'GBP' => array())),
   );
//===========================================================================

//===========================================================================
function BWWC__GetPluginNameVersionEdition($please_donate = true)
{
  $return_data = '<h2 style="border-bottom:1px solid #DDD;padding-bottom:10px;margin-bottom:20px;">' .
            BWWC_PLUGIN_NAME . ', version: <span style="color:#EE0000;">' .
            BWWC_VERSION. '</span> [<span style="color:#EE0000;background-color:#FFFF77;">&nbsp;' .
            BWWC_EDITION . '&nbsp;</span> edition]' .
          '</h2>';


  if ($please_donate)
  {
    $return_data .= '<p style="border:1px solid #890e4e;padding:5px 10px;color:#004400;background-color:#FFF;"><u>Please donate BTC to</u>:&nbsp;&nbsp;<span style="color:#d21577;font-size:110%;font-weight:bold;">12fFTMkeu3mcunCtGHtWb7o5BcWA9eFx7R</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u>or via Paypal to</u>:&nbsp;&nbsp;<span style="color:#d21577;font-size:110%;font-weight:bold;">donate@bitcoinway.com</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-size:95%;">All supporters will be acknowledged and listed within plugin repository. Please note that if you ever donated - you may use your donation as a credit toward <a href="' . BWWC__GetProUrl() . '"><b>Pro version</b></a>.</span></p>';
  }

  return $return_data;
}
//===========================================================================

//===========================================================================
function BWWC__GetProUrl() { return 'http://bitcoinway.com/products/'; }
function BWWC__GetProLabel()
{
   return '<span style="background-color:#FF4;color:#F44;border:1px solid #F44;padding:2px 6px;font-family:\'Open Sans\',sans-serif;font-size:14px;border-radius:6px;"><a href="' . BWWC__GetProUrl() . '">PRO Only</a></span>';
}
//===========================================================================

//===========================================================================
// These are coming from plugin-specific table.
function BWWC__get_persistent_settings ($key=false)
{
////// PERSISTENT SETTINGS CURRENTLY UNUSED
return array();
//////
  global $wpdb;

  $persistent_settings_table_name = $wpdb->prefix . 'bwwc_persistent_settings';
  $sql_query = "SELECT * FROM `$persistent_settings_table_name` WHERE `id` = '1';";

  $row = $wpdb->get_row($sql_query, ARRAY_A);
  if ($row)
  {
    $settings = @unserialize($row['settings']);
    if ($key)
      return $settings[$key];
    else
      return $settings;
  }
  else
    return array();
}
//===========================================================================

//===========================================================================
function BWWC__update_persistent_settings ($bwwc_use_these_settings_array=false)
{
////// PERSISTENT SETTINGS CURRENTLY UNUSED
return;
//////
  global $wpdb;

  $persistent_settings_table_name = $wpdb->prefix . 'bwwc_persistent_settings';

  if (!$bwwc_use_these_settings)
    $bwwc_use_these_settings = array();

  $db_ready_settings = BWWC__safe_string_escape (serialize($bwwc_use_these_settings_array));

  $wpdb->update($persistent_settings_table_name, array('settings' => $db_ready_settings), array('id' => '1'), array('%s'));
}
//===========================================================================

//===========================================================================
// Wipe existing table's contents and recreate first record with all defaults.
function BWWC__reset_all_persistent_settings ()
{
////// PERSISTENT SETTINGS CURRENTLY UNUSED
return;
//////

  global $wpdb;
  global $g_BWWC__config_defaults;

  $persistent_settings_table_name = $wpdb->prefix . 'bwwc_persistent_settings';

  $initial_settings = BWWC__safe_string_escape (serialize($g_BWWC__config_defaults));

  $query = "TRUNCATE TABLE `$persistent_settings_table_name`;";
  $wpdb->query ($query);

  $query = "INSERT INTO `$persistent_settings_table_name`
      (`id`, `settings`)
        VALUES
      ('1', '$initial_settings');";
  $wpdb->query ($query);
}
//===========================================================================

//===========================================================================
function BWWC__get_settings ($key=false)
{
  global   $g_BWWC__plugin_directory_url;
  global   $g_BWWC__config_defaults;

  $bwwc_settings = get_option (BWWC_SETTINGS_NAME);
  if (!is_array($bwwc_settings))
    $bwwc_settings = array();


  if ($key)
    return (@$bwwc_settings[$key]);
  else
    return ($bwwc_settings);
}
//===========================================================================

//===========================================================================
function BWWC__update_settings ($bwwc_use_these_settings=false, $also_update_persistent_settings=false)
{
   if ($bwwc_use_these_settings)
      {
      if ($also_update_persistent_settings)
        BWWC__update_persistent_settings ($bwwc_use_these_settings);

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

  // ---------------------------------------
  // Post-process variables.

  // Array of MPK's. Single MPK = element with idx=0
  $bwwc_settings['electrum_mpks'] = preg_split("/[\s,]+/", $bwwc_settings['electrum_mpk_saved']);
  // ---------------------------------------


  if ($also_update_persistent_settings)
    BWWC__update_persistent_settings ($bwwc_settings);

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
function BWWC__reset_partial_settings ($also_reset_persistent_settings=false)
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

  if ($also_reset_persistent_settings)
    BWWC__update_persistent_settings ($bwwc_settings);
}
//===========================================================================

//===========================================================================
function BWWC__reset_all_settings ($also_reset_persistent_settings=false)
{
  global   $g_BWWC__config_defaults;

  update_option (BWWC_SETTINGS_NAME, $g_BWWC__config_defaults);

  if ($also_reset_persistent_settings)
    BWWC__reset_all_persistent_settings ();
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
/*
    ----------------------------------
    : Table 'btc_addresses' :
    ----------------------------------
      status                "unused"      - never been used address with last known zero balance
                            "assigned"    - order was placed and this address was assigned for payment
                            "revalidate"  - assigned/expired, unused or unknown address suddenly got non-zero balance in it. Revalidate it for possible late order payment against meta_data.
                            "used"        - order was placed and this address and payment in full was received. Address will not be used again.
                            "xused"       - address was used (touched with funds) by unknown entity outside of this application. No metadata is present for this address, will not be able to correlated it with any order.
                            "unknown"     - new address was generated but cannot retrieve balance due to blockchain API failure.
*/
function BWWC__create_database_tables ($bwwc_settings)
{
  global $wpdb;

  $bwwc_settings = BWWC__get_settings();
  $must_update_settings = false;

  ///$persistent_settings_table_name       = $wpdb->prefix . 'bwwc_persistent_settings';
  ///$electrum_wallets_table_name          = $wpdb->prefix . 'bwwc_electrum_wallets';
  $btc_addresses_table_name             = $wpdb->prefix . 'bwwc_btc_addresses';

  if($wpdb->get_var("SHOW TABLES LIKE '$btc_addresses_table_name'") != $btc_addresses_table_name)
      $b_first_time = true;
  else
      $b_first_time = false;

 //----------------------------------------------------------
 // Create tables
  /// NOT NEEDED YET
  /// $query = "CREATE TABLE IF NOT EXISTS `$persistent_settings_table_name` (
  ///   `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ///   `settings` text,
  ///   PRIMARY KEY  (`id`)
  ///   );";
  /// $wpdb->query ($query);

  /// $query = "CREATE TABLE IF NOT EXISTS `$electrum_wallets_table_name` (
  ///   `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ///   `master_public_key` varchar(255) NOT NULL,
  ///   PRIMARY KEY  (`id`),
  ///   UNIQUE KEY  `master_public_key` (`master_public_key`)
  ///   );";
  /// $wpdb->query ($query);

  $query = "CREATE TABLE IF NOT EXISTS `$btc_addresses_table_name` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `btc_address` char(36) NOT NULL,
    `origin_id` char(128) NOT NULL DEFAULT '',
    `index_in_wallet` bigint(20) NOT NULL DEFAULT '0',
    `status` char(16)  NOT NULL DEFAULT 'unknown',
    `last_assigned_to_ip` char(16) NOT NULL DEFAULT '0.0.0.0',
    `assigned_at` bigint(20) NOT NULL DEFAULT '0',
    `total_received_funds` DECIMAL( 16, 8 ) NOT NULL DEFAULT '0.00000000',
    `received_funds_checked_at` bigint(20) NOT NULL DEFAULT '0',
    `address_meta` MEDIUMBLOB NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `btc_address` (`btc_address`),
    KEY `index_in_wallet` (`index_in_wallet`),
    KEY `origin_id` (`origin_id`),
    KEY `status` (`status`)
    );";
  $wpdb->query ($query);
  //----------------------------------------------------------

	// upgrade bwwc_btc_addresses table, add additional indexes
  if (!$b_first_time)
  {
    $version = floatval($bwwc_settings['database_schema_version']);

    if ($version < 1.1)
    {

      $query = "ALTER TABLE `$btc_addresses_table_name` ADD INDEX `origin_id` (`origin_id` ASC) , ADD INDEX `status` (`status` ASC)";
      $wpdb->query ($query);
      $bwwc_settings['database_schema_version'] = 1.1;
      $must_update_settings = true;
    }

    if ($version < 1.2)
    {

      $query = "ALTER TABLE `$btc_addresses_table_name` DROP INDEX `index_in_wallet`, ADD INDEX `index_in_wallet` (`index_in_wallet` ASC)";
      $wpdb->query ($query);
      $bwwc_settings['database_schema_version'] = 1.2;
      $must_update_settings = true;
    }

    if ($version < 1.3)
    {

      $query = "ALTER TABLE `$btc_addresses_table_name` CHANGE COLUMN `origin_id` `origin_id` char(128)";
      $wpdb->query ($query);
      $bwwc_settings['database_schema_version'] = 1.3;
      $must_update_settings = true;

      $mpk = @$bwwc_settings['gateway_settings']['electrum_master_public_key'];
      if ($mpk)
      {
        // Replace hashed values of MPK in DB with real MPK values.
        $mpk_old_value = 'electrum.mpk.' . md5($mpk);
        // UPDATE table_name SET field = REPLACE(field, 'foo', 'bar') WHERE INSTR(field, 'foo') > 0;
        // UPDATE [table_name] SET [field_name] = REPLACE([field_name], "foo", "bar");
        $query = "UPDATE `$btc_addresses_table_name` SET `origin_id` = '$mpk' WHERE `origin_id` = '$mpk_old_value'";
        $wpdb->query ($query);

        // Copy settings from old location to new, if new is empty.
        if (!@$bwwc_settings['electrum_mpk_saved'])
        {
          $bwwc_settings['electrum_mpk_saved'] = $mpk;
          // 'BWWC__update_settings()' will populate $bwwc_settings['electrum_mpks'].
        }
      }
    }

    if ($version < 1.4)
    {

      $query = "ALTER TABLE `$btc_addresses_table_name` MODIFY `address_meta` MEDIUMBLOB";
      $wpdb->query ($query);
      $bwwc_settings['database_schema_version'] = 1.4;
      $must_update_settings = true;
    }

  }

  if ($must_update_settings)
  {
	  BWWC__update_settings ($bwwc_settings);
	}

  //----------------------------------------------------------
  // Seed DB tables with initial set of data
  /* PERSISTENT SETTINGS CURRENTLY UNUNSED
  if ($b_first_time || !is_array(BWWC__get_persistent_settings()))
  {
    // Wipes table and then creates first record and populate it with defaults
    BWWC__reset_all_persistent_settings();
  }
  */
   //----------------------------------------------------------
}
//===========================================================================

//===========================================================================
// NOTE: Irreversibly deletes all plugin tables and data
function BWWC__delete_database_tables ()
{
  global $wpdb;

  ///$persistent_settings_table_name       = $wpdb->prefix . 'bwwc_persistent_settings';
  ///$electrum_wallets_table_name          = $wpdb->prefix . 'bwwc_electrum_wallets';
  $btc_addresses_table_name    = $wpdb->prefix . 'bwwc_btc_addresses';

  ///$wpdb->query("DROP TABLE IF EXISTS `$persistent_settings_table_name`");
  ///$wpdb->query("DROP TABLE IF EXISTS `$electrum_wallets_table_name`");
  $wpdb->query("DROP TABLE IF EXISTS `$btc_addresses_table_name`");
}
//===========================================================================

