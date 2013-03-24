<?php
/*








Plugin Name: Bitcoin Payments for WooCommerce
Plugin URI: http://www.bitcoinway.com/
Description: Bitcoin Payments for WooCommerce plugin allows you to accept payments in bitcoins for physical and digital products at your WooCommerce-powered online store.
Version: 1.25
Author: BitcoinWay
Author URI: http://www.bitcoinway.com/
License: GNU General Public License 2.0 (GPL) http://www.gnu.org/licenses/gpl.html

*/



// Include everything
include (dirname(__FILE__) . '/bwwc-include-all.php');

//---------------------------------------------------------------------------
// Add hooks and filters

// create custom plugin settings menu
add_action( 'admin_menu',                   'BWWC_create_menu' );

register_activation_hook(__FILE__,          'BWWC_activate');
register_deactivation_hook(__FILE__,        'BWWC_deactivate');
register_uninstall_hook(__FILE__,           'BWWC_uninstall');

BWWC_set_lang_file();
//---------------------------------------------------------------------------

//===========================================================================
// activating the default values
function BWWC_activate()
{
    global  $g_BWWC__config_defaults;

    $bwwc_default_options = $g_BWWC__config_defaults;

    // This will overwrite default options with already existing options but leave new options (in case of upgrading to new version) untouched.
    $bwwc_settings = BWWC__get_settings ();
    if (is_array ($bwwc_settings))
      {
      foreach ($bwwc_settings as $key=>$value)
         $bwwc_default_options[$key] = $value;
      }

   update_option (BWWC_SETTINGS_NAME, $bwwc_default_options);

   // Re-get new settings.
   $bwwc_settings = BWWC__get_settings ();

   // Create necessaery database tables if not already exists...
   BWWC__create_database_tables ($bwwc_settings);
}
//===========================================================================

//===========================================================================
// deactivating
function BWWC_deactivate ()
{
    // Do deactivation cleanup. Do not delete previous settings in case user will reactivate plugin again...
    // ...
}
//===========================================================================

//===========================================================================
// uninstalling
function BWWC_uninstall ()
{
    // delete all settings.
    delete_option(BWWC_SETTINGS_NAME);

    // delete all tables and data.
    BWWC__delete_database_tables ();
}
//===========================================================================

//===========================================================================
function BWWC_create_menu()
{

    // create new top-level menu
    // http://www.fileformat.info/info/unicode/char/e3f/index.htm
    add_menu_page (
        __('Woo Bitcoin', BWWC_I18N_DOMAIN),                    // Page title
        __('Bitcoin', BWWC_I18N_DOMAIN),                        // Menu Title - lower corner of admin menu
        'administrator',                                        // Capability
        'bwwc-settings',                                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
        'BWWC__render_general_settings_page',                   // Function
        plugins_url('/images/bitcoin_16x.png', __FILE__)                // Icon URL
        );

    add_submenu_page (
        'bwwc-settings',                                        // Parent
        __("WooCommerce Bitcoin Payments Gateway", BWWC_I18N_DOMAIN),                   // Page title
        __("General Settings", BWWC_I18N_DOMAIN),               // Menu Title
        'administrator',                                        // Capability
        'bwwc-settings',                                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
        'BWWC__render_general_settings_page'                    // Function
        );


}
//===========================================================================

//===========================================================================
// load language files
function BWWC_set_lang_file()
{
    # set the language file
    $currentLocale = get_locale();
    if(!empty($currentLocale))
    {
        $moFile = dirname(__FILE__) . "/lang/" . $currentLocale . ".mo";
        if (@file_exists($moFile) && is_readable($moFile))
        {
            load_textdomain(BWWC_I18N_DOMAIN, $moFile);
        }

    }
}
//===========================================================================

