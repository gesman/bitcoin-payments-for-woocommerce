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
      BWWC__update_settings ();
echo <<<HHHH
<div align="center" style="background-color:#FFFFE0;padding:5px;font-size:120%;border: 1px solid #E6DB55;margin:5px;border-radius:3px;">
Settings updated!
</div>
HHHH;
      }
   else if (isset($_POST['button_reset_bwwc_settings']))
      {
      BWWC__reset_all_settings ();
echo <<<HHHH
<div align="center" style="background-color:#FFFFE0;padding:5px;font-size:120%;border: 1px solid #E6DB55;margin:5px;border-radius:3px;">
All settings reverted to all defaults
</div>
HHHH;
      }
   else if (isset($_POST['button_reset_partial_bwwc_settings']))
      {
      BWWC__reset_partial_settings ();
echo <<<HHHH
<div align="center" style="background-color:#FFFFE0;padding:5px;font-size:120%;border: 1px solid #E6DB55;margin:5px;border-radius:3px;">
Settings on this page reverted to defaults
</div>
HHHH;
      }
   else if (isset($_POST['validate_bwwc-license']))
      {
      BWWC__update_settings ();
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
                <th scope="row">API Key:</th>
                <td><input type="text" name="api-key" value="<?php echo $bwwc_settings['api-key']; ?>" /></td>
                <td>Api key to enable integration with other systems</td>
            </tr>

        </table>

        <p class="submit">
            <input type="submit" class="button-primary"    name="button_update_bwwc_settings"        value="<?php _e('Save Changes') ?>"             />
            <input type="submit" class="button-secondary"  style="color:red;" name="button_reset_partial_bwwc_settings" value="<?php _e('Reset values to defaults') ?>" onClick="return confirm('Are you sure you want to reset settings on this page to defaults?');" />
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
