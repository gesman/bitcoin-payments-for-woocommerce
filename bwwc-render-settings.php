<?php
/*
Bitcoin Payments for WooCommerce
http://www.bitcoinway.com/
*/

// Include everything
include (dirname(__FILE__) . '/bwwc-include-all.php');

//===========================================================================
function BWWC__render_general_settings_page ()   { BWWC__render_settings_page   ('general'); }
function BWWC__render_advanced_settings_page ()  { BWWC__render_settings_page   ('advanced'); }
//===========================================================================

//===========================================================================
function BWWC__render_settings_page ($menu_page_name)
{
   $bwwc_settings = BWWC__get_settings ();

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
  $gateway_status_message = "";
  $gateway_valid_for_use = BWWC__is_gateway_valid_for_use($gateway_status_message);
  if (!$gateway_valid_for_use)
  {
    $gateway_status_message =
    '<p style="border:1px solid #DDD;padding:5px 10px;font-weight:bold;color:#EE0000;background-color:#FFFFAA;">' .
    "Bitcoin Payment Gateway is NOT operational (try to re-enter and save settings): " . $gateway_status_message .
    '</p>';
  }
  else
  {
    $gateway_status_message =
    '<p style="border:1px solid #DDD;padding:5px 10px;font-weight:bold;color:#004400;background-color:#CCFFCC;">' .
    "Bitcoin Payment Gateway is operational" .
    '</p>';
  }

  $currency_code = false;
  if (function_exists('get_woocommerce_currency'))
    $currency_code = @get_woocommerce_currency();
  if (!$currency_code || $currency_code=='BTC')
    $currency_code = 'USD';

  $exchange_rate_message =
    '<p style="border:1px solid #DDD;padding:5px 10px;background-color:#cceeff;">' .
    BWWC__get_exchange_rate_per_bitcoin ($currency_code, 'getfirst', true) .
    '</p>';

   echo '<div class="wrap">';

   switch ($menu_page_name)
      {
      case 'general'     :
        echo  BWWC__GetPluginNameVersionEdition(true);
        echo  $gateway_status_message . $exchange_rate_message;
        BWWC__render_general_settings_page_html();
        break;

      case 'advanced'    :
        echo  BWWC__GetPluginNameVersionEdition(false);
        echo  $gateway_status_message . $exchange_rate_message;
        BWWC__render_advanced_settings_page_html();
        break;

      default            :
        break;
      }

   echo '</div>'; // wrap
}
//===========================================================================

//===========================================================================
function BWWC__render_general_settings_page_html ()
{
  $bwwc_settings = BWWC__get_settings ();
  global $g_BWWC__cron_script_url;

?>

    <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
      <p class="submit">
        <input type="submit" class="button-primary"    name="button_update_bwwc_settings"        value="<?php _e('Save Changes') ?>"             />
        <input type="submit" class="button-secondary"  style="color:red;" name="button_reset_partial_bwwc_settings" value="<?php _e('Reset settings') ?>" onClick="return confirm('Are you sure you want to reset settings on this page?');" />
      </p>
      <table class="form-table">


        <tr valign="top">
          <th scope="row">Delete all plugin-specific settings, database tables and data on uninstall:</th>
          <td>
            <input type="hidden" name="delete_db_tables_on_uninstall" value="0" /><input type="checkbox" name="delete_db_tables_on_uninstall" value="1" <?php if ($bwwc_settings['delete_db_tables_on_uninstall']) echo 'checked="checked"'; ?> />
            <p class="description">If checked - all plugin-specific settings, database tables and data will be removed from Wordpress database upon plugin uninstall (but not upon deactivation or upgrade).</p>
          </td>
        </tr>

        <tr valign="top">
          <th scope="row">Bitcoin Service Provider:</th>
          <td>
            <select name="service_provider" class="select ">
              <option <?php if ($bwwc_settings['service_provider'] == 'electrum_wallet') echo 'selected="selected"'; ?> value="electrum_wallet">Your own Electrum wallet</option>
              <option <?php if ($bwwc_settings['service_provider'] == 'blockchain_info') echo 'selected="selected"'; ?> value="blockchain_info">Blockchain.info API (deprecated - use Electrum instead)</option>
            </select>
            <p class="description">
              Please select your Bitcoin service provider and press [Save changes]. Then fill-in necessary details and press [Save changes] again.
              <br />Recommended setting: <b>Your own Electrum wallet</b>.
            </p>
          </td>
        </tr>

        <tr valign="top">
          <th scope="row">Electrum Master Public Key (MPK):</th>
          <td>
            <textarea style="width:75%;" name="electrum_mpk_saved"><?php echo $bwwc_settings['electrum_mpk_saved']; ?></textarea>
            <p class="description">
              <ol class="description">
                <li>
                  Launch Electrum wallet and get Master Public Key value from:
                  Wallet -> Master Public Key, or:
                  <br />older version of Electrum: Preferences -> Import/Export -> Master Public Key -> Show.
                </li>
                <li>
                  Copy long number string and paste it in this field.
                </li>
                <li>
                  Change "gap limit" value to bigger value (to make sure youll see the total balance on your wallet):
                  <br />Click on "Console" tab and run this command: <tt>wallet.storage.put('gap_limit',100)</tt>
                </li>
                <li>
                  Restart Electrum wallet to activate new gap limit. You may do it later at any time - gap limit does not affect functionlity of your online store.
                  <br />If your online store receives lots of orders in bitcoins - you might need to set gap limit to even bigger value.
                </li>
              </ol>
            </p>
          </td>
        </tr>

        <tr valign="top">
          <th scope="row">Number of confirmations required before accepting payment:</th>
          <td>
            <input type="text" name="confs_num" value="<?php echo $bwwc_settings['confs_num']; ?>" size="4" />
            <p class="description">
              After a transaction is broadcast to the Bitcoin network, it may be included in a block that is published
              to the network. When that happens it is said that one <a href="https://en.bitcoin.it/wiki/Confirmation"><b>confirmation</b></a> has occurred for the transaction.
              With each subsequent block that is found, the number of confirmations is increased by one. To protect against double spending, a transaction should not be considered as confirmed until a certain number of blocks confirm, or verify that transaction.
              6 is considered very safe number of confirmations, although it takes longer to confirm.
            </p>
          </td>
        </tr>

        <tr valign="top">
          <th scope="row">Bitcoin Exchange rate calculation type:</th>
          <td>
            <select name="exchange_rate_type" class="select ">
              <option <?php if ($bwwc_settings['exchange_rate_type'] == 'vwap') echo 'selected="selected"'; ?> value="vwap">Weighted Average</option>
              <option <?php if ($bwwc_settings['exchange_rate_type'] == 'realtime') echo 'selected="selected"'; ?> value="realtime">Real Time</option>
              <option <?php if ($bwwc_settings['exchange_rate_type'] == 'bestrate') echo 'selected="selected"'; ?> value="bestrate">Most profitable</option>
            </select>
            <p class="description">
              Weighted Average (recommended): <a href="http://en.wikipedia.org/wiki/Volume-weighted_average_price">weighted average</a> rates polled from a number of exchange services
              <br />Real time: the most recent transaction rates polled from a number of exchange services.
              <br />Most profitable: pick better exchange rate of all indicators (most favorable for merchant). Calculated as: MIN (Weighted Average, Real time)
            </p>
          </td>
        </tr>

        <tr valign="top">
          <th scope="row">Exchange rate multiplier:</th>
          <td>
            <input type="text" name="exchange_multiplier" value="<?php echo $bwwc_settings['exchange_multiplier']; ?>" size="4" />
            <p class="description">
              Extra multiplier to apply to convert store default currency to bitcoin price.
              <br />Example: 1.05 - will add extra 5% to the total price in bitcoins.
              May be useful to compensate for market volatility or for merchant's loss to fees when converting bitcoins to local currency,
              or to encourage customer to use bitcoins for purchases (by setting multiplier to < 1.00 values).
            </p>
          </td>
        </tr>

        <tr valign="top">
            <th scope="row">Extreme privacy mode enabled?</th>
            <td>


              <select name="PRO_EDITION_ONLY" class="select">
                <option selected="selected" disabled="disabled" value="0">No (default)</option>
              </select> <?php echo BWWC__GetProLabel(); ?>


              <p class="description">
                <b>No</b> (default, recommended) - will allow to recycle bitcoin addresses that been generated for each placed order but never received any payments. The drawback of this approach is that potential snoop can generate many fake (never paid for) orders to discover sequence of bitcoin addresses that belongs to the wallet of this store and then track down sales through blockchain analysis. The advantage of this option is that it very efficiently reuses empty (zero-balance) bitcoin addresses within the wallet, allowing only 1 sale per address without growing the wallet size (Electrum "gap" value).
                <br />
                <b>Yes</b> - this will guarantee to generate unique bitcoin address for every order (real, accidental or fake). This option will provide the most anonymity and privacy to the store owner's wallet. The drawback is that it will likely leave a number of addresses within the wallet never used (and hence will require setting very high 'gap limit' within the Electrum wallet much sooner).
                <br />It is recommended to regenerate new wallet after number of used bitcoin addresses reaches 1000. Wallets with very high gap limits (>1000) are very slow to sync with blockchain and they put an extra load on the network. <br />
                Extreme privacy mode offers the best anonymity and privacy to the store albeit with the drawbacks of potentially flooding your Electrum wallet with expired and zero-balance addresses. Hence, for vast majority of cases (where you just need a secure way to operate bitcoin based store) it is suggested to set this option to 'No').<br />
                <b>Note</b>: It is possible to change this option at any time and it will take effect immediately.
              </p>
            </td>
        </tr>

        <tr valign="top">
          <th scope="row">Auto-complete paid orders:</th>
          <td>
            <input type="hidden" name="autocomplete_paid_orders" value="0" /><input type="checkbox" name="autocomplete_paid_orders" value="1" <?php if ($bwwc_settings['autocomplete_paid_orders']) echo 'checked="checked"'; ?> />
            <p class="description">If checked - fully paid order will be marked as 'completed' and '<i>Your order is complete</i>' email will be immediately delivered to customer.
            	<br />If unchecked: store admin will need to mark order as completed manually - assuming extra time needed to ship physical product after payment is received.
            	<br />Note: virtual/downloadable products will automatically complete upon receiving full payment (so this setting does not have effect in this case).
            </p>
          </td>
        </tr>

        <tr valign="top">
            <th scope="row">Cron job type:</th>
            <td>
              <select name="enable_soft_cron_job" class="select ">
                <option <?php if ($bwwc_settings['enable_soft_cron_job'] == '1') echo 'selected="selected"'; ?> value="1">Soft Cron (Wordpress-driven)</option>
                <option <?php if ($bwwc_settings['enable_soft_cron_job'] != '1') echo 'selected="selected"'; ?> value="0">Hard Cron (Cpanel-driven)</option>
              </select>
              <p class="description">
                <?php if ($bwwc_settings['enable_soft_cron_job'] != '1') echo '<p style="background-color:#FFC;color:#2A2;"><b>NOTE</b>: Hard Cron job is enabled: make sure to follow instructions below to enable hard cron job at your hosting panel.</p>'; ?>
                Cron job will take care of all regular bitcoin payment processing tasks, like checking if payments are made and automatically completing the orders.<br />
                <b>Soft Cron</b>: - Wordpress-driven (runs on behalf of a random site visitor).
                <br />
                <b>Hard Cron</b>: - Cron job driven by the website hosting system/server (usually via CPanel). <br />
                When enabling Hard Cron job - make this script to run every 5 minutes at your hosting panel cron job scheduler:<br />
                <?php echo '<tt style="background-color:#FFA;color:#B00;padding:0px 6px;">wget -O /dev/null ' . $g_BWWC__cron_script_url . '?hardcron=1</tt>'; ?>
                <br /><b style="color:red;">NOTE:</b> Cron jobs <b>might not work</b> if your site is password protected with HTTP Basic auth or other methods. This will result in WooCommerce store not seeing received payments (even though funds will arrive correctly to your bitcoin addresses).
                <br /><u>Note:</u> You will need to deactivate/reactivate plugin after changing this setting for it to have effect.<br />
                "Hard" cron jobs may not be properly supported by all hosting plans (many shared hosting plans has restrictions in place).
                <br />For secure, fast hosting service optimized for wordpress and 100% compatibility with WooCommerce and Bitcoin payments we recommend <b><a href="http://hostrum.com/" target="_blank">Hostrum Hosting</a></b>.
              </p>
            </td>
        </tr>

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
function BWWC__render_advanced_settings_page_html ()
{
   $bwwc_settings = BWWC__get_settings ();


?>


<p style="text-align:center;"><?php echo BWWC__GetProLabel(); ?></p>
<p><h3>Advanced Settings section gives you many more options to configure and optimize all aspects and functionality of your bitcoin store.</h3>
  Please note that if you are among many who made a donation toward the development of BitcoinWay software - you will receive an equivalent credit toward the
  <a href="<?php echo BWWC__GetProUrl(); ?>"><b>Pro version</b></a>.
</p>
<h3 style="text-align:center;color:#090;">Get the <a href="<?php echo BWWC__GetProUrl(); ?>"><b>PRO version</b></a></h3>


      </table>

      <p class="submit">
          <input type="submit" class="button-primary"    name="button_update_bwwc_settings"        value="{$value_save_changes}"             />
          <input type="submit" class="button-secondary"  style="color:red;" name="button_reset_partial_bwwc_settings" value="{$value_reset_settings}" onClick="return confirm('Are you sure you want to reset settings on this page?');" />
      </p>
  </form>
TTT;
  echo $html_out;
//{{{/PLACEHOLDER}}} ?> ///+{EDITION==Pro}///

<?php
}
//===========================================================================
