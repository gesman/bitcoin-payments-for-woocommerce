<?php
/*
Bitcoin Payments for WooCommerce
http://www.bitcoinway.com/
*/

// Include everything
include (dirname(__FILE__) . '/bwwc-include-all.php');

//===========================================================================
function BWWC__render_general_settings_page ()          { BWWC__render_settings_page   ('general'); }
function BWWC__render_blockchain_info_settings_page ()  { BWWC__render_settings_page   ('blockchain.info'); }
//===========================================================================

//===========================================================================
function BWWC__render_settings_page ($menu_page_name)
{
   if (isset ($_POST['button_update_bwwc_settings']))
      {
      BWWC__update_settings ("", false);
echo <<<HHHH
<div align="center" style="background-color:#FFFFE0;padding:5px;font-size:120%;border: 1px solid #E6DB55;margin:5px;border-radius:3px;">
Settings updated!
</div>
HHHH;
      }
   else if (isset($_POST['button_reset_bwwc_settings']))
      {
      BWWC__reset_all_settings (false);
echo <<<HHHH
<div align="center" style="background-color:#FFFFE0;padding:5px;font-size:120%;border: 1px solid #E6DB55;margin:5px;border-radius:3px;">
All settings reverted to all defaults
</div>
HHHH;
      }
   else if (isset($_POST['button_reset_partial_bwwc_settings']))
      {
      BWWC__reset_partial_settings (false);
echo <<<HHHH
<div align="center" style="background-color:#FFFFE0;padding:5px;font-size:120%;border: 1px solid #E6DB55;margin:5px;border-radius:3px;">
Settings on this page reverted to defaults
</div>
HHHH;
      }
   else if (isset($_POST['validate_bwwc-license']))
      {
      BWWC__update_settings ("", false);
      }

   // Output full admin settings HTML
   echo '<div class="wrap">';
   echo     BWWC__GetPluginNameVersionEdition();

   switch ($menu_page_name)
      {
      case 'general'            :   BWWC__render_general_settings_page_html();          break;
      case 'blockchain.info'    :   BWWC__render_blockchain_info_settings_page_html();  break;
      default                   :   break;
      }

   echo '</div>'; // wrap
}
//===========================================================================

//===========================================================================
function BWWC__render_general_settings_page_html ()
{
   $bwwc_settings = BWWC__get_settings ();

?>

    <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
        <table class="form-table">


            <tr valign="top">
                <th scope="row">Delete all plugin-specific settings, database tables and data on uninstall:</th>
                <td><input type="hidden" name="delete_db_tables_on_uninstall" value="0" /><input type="checkbox" name="delete_db_tables_on_uninstall" value="1" <?php if ($bwwc_settings['delete_db_tables_on_uninstall']) echo 'checked="checked"'; ?> /></td>
                <td>If checked - all plugin-specific settings, database tables and data will be removed from Wordpress database upon plugin uninstall<br />(but not upon deactivation or upgrade).</td>
            </tr>
            <tr><td colspan="3" style="height:1px;border-bottom:1px solid #DDD;"></td></tr>

            <tr valign="top">
                <th scope="row">Enable soft (wordpress) cron job:</th>
                <td><input type="hidden" name="enable_soft_cron_job" value="0" /><input type="checkbox" name="enable_soft_cron_job" value="1" <?php if ($bwwc_settings['enable_soft_cron_job']) echo 'checked="checked"'; ?> /></td>
                <td>
                  If checked - Wordpress-driven cron job will take care of all bitcoin payment processing tasks, like checking if payments are made and automatically completing the orders.
                  <br />Alternatively (better option) is to enable "hard" cron job driven by the website hosting system (usually via CPanel). "Hard" cron jobs are not supported by all hosting services.
                  <br /><b>Note:</b> you will need to deactivate/reactivate plugin after changing this setting for it to have effect.
                </td>
            </tr>
            <tr><td colspan="3" style="height:1px;border-bottom:1px solid #DDD;"></td></tr>

        </table>

        <p class="submit">
            <input type="submit" class="button-primary"    name="button_update_bwwc_settings"        value="<?php _e('Save Changes') ?>"             />
            <input type="submit" class="button-secondary"  style="color:red;" name="button_reset_partial_bwwc_settings" value="<?php _e('Reset settings') ?>" onClick="return confirm('Are you sure you want to reset settings on this page?');" />
        </p>
    </form>
<?php
}
//===========================================================================

//===========================================================================
function BWWC__render_blockchain_info_settings_page_html ()
{
    echo "blockchain.info settings...";
}
//===========================================================================
