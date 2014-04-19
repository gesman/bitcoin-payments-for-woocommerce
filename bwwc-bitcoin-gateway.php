<?php
/*
Bitcoin Payments for WooCommerce
http://www.bitcoinway.com/
*/


//---------------------------------------------------------------------------
add_action('plugins_loaded', 'BWWC__plugins_loaded__load_bitcoin_gateway', 0);
//---------------------------------------------------------------------------

//###########################################################################
// Hook payment gateway into WooCommerce

function BWWC__plugins_loaded__load_bitcoin_gateway ()
{

    if (!class_exists('WC_Payment_Gateway'))
    	// Nothing happens here is WooCommerce is not loaded
    	return;

	//=======================================================================
	/**
	 * Bitcoin Payment Gateway
	 *
	 * Provides a Bitcoin Payment Gateway
	 *
	 * @class 		BWWC_Bitcoin
	 * @extends		WC_Payment_Gateway
	 * @version
	 * @package
	 * @author 		BitcoinWay
	 */
	class BWWC_Bitcoin extends WC_Payment_Gateway
	{
		//-------------------------------------------------------------------
	    /**
	     * Constructor for the gateway.
	     *
	     * @access public
	     * @return void
	     */
		public function __construct()
		{
      $this->id				= 'bitcoin';
      $this->icon 			= plugins_url('/images/btc_buyitnow_32x.png', __FILE__);	// 32 pixels high
      $this->has_fields 		= false;
      $this->method_title     = __( 'Bitcoin', 'woocommerce' );

			// Load the settings.
			$this->init_settings();

			// Define user set variables
			$this->title 		= $this->settings['title'];	// The title which the user is shown on the checkout – retrieved from the settings which init_settings loads.
			$this->service_provider = $this->settings['service_provider'];
			$this->electrum_master_public_key = $this->settings['electrum_master_public_key'];
			$this->bitcoin_addr_merchant = $this->settings['bitcoin_addr_merchant'];	// Forwarding address where all product payments will aggregate.
			
			$this->confirmations = $this->settings['confirmations'];
			$this->exchange_rate_type = $this->settings['exchange_rate_type'];
			$this->exchange_multiplier = $this->settings['exchange_multiplier'];
			$this->description 	= $this->settings['description'];	// Short description about the gateway which is shown on checkout.
			$this->instructions = $this->settings['instructions'];	// Detailed payment instructions for the buyer.
			$this->instructions_multi_payment_str  = __('You may send payments from multiple accounts to reach the total required.', 'woocommerce');
			$this->instructions_single_payment_str = __('You must pay in a single payment in full.', 'woocommerce');

			// Load the form fields.
			$this->init_form_fields();

			// Actions
      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) )
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
      else
				add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options')); // hook into this action to save options in the backend

	    add_action('woocommerce_thankyou_' . $this->id, array(&$this, 'BWWC__thankyou_page')); // hooks into the thank you page after payment

	    	// Customer Emails
	    add_action('woocommerce_email_before_order_table', array(&$this, 'BWWC__email_instructions'), 10, 2); // hooks into the email template to show additional details

			// Hook IPN callback logic
			if (version_compare (WOOCOMMERCE_VERSION, '2.0', '<'))
				add_action('init', array(&$this, 'BWWC__maybe_bitcoin_ipn_callback'));
			else
				add_action('woocommerce_api_' . strtolower(get_class($this)), array($this,'BWWC__maybe_bitcoin_ipn_callback'));

			// Validate currently set currency for the store. Must be among supported ones.
			if (!$this->BWWC__is_gateway_valid_for_use()) $this->enabled = false;
	    }
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Check if this gateway is enabled and available for the store's default currency
	     *
	     * @access public
	     * @return bool
	     */
	    function BWWC__is_gateway_valid_for_use(&$ret_reason_message=NULL)
	    {
	    	$valid = true;

	    	//----------------------------------
	    	// Validate settings
	    	if (!$this->service_provider)
	    	{
	    		$reason_message = __("Bitcoin Service Provider is not selected", 'woocommerce');
	    		$valid = false;
	    	}
	    	else if ($this->service_provider=='blockchain.info')
	    	{
	    		if ($this->bitcoin_addr_merchant == '')
	    		{
		    		$reason_message = __("Your personal bitcoin address is not selected", 'woocommerce');
		    		$valid = false;
	    		}
	    		else if ($this->bitcoin_addr_merchant == '1H9uAP3x439YvQDoKNGgSYCg3FmrYRzpD2')
	    		{
		    		$reason_message = __("Your personal bitcoin address is invalid. The address specified is Bitcoinway.com's donation address :)", 'woocommerce');
		    		$valid = false;
	    		}
	    	}
	    	else if ($this->service_provider=='electrum-wallet')
	    	{
	    		if (!$this->electrum_master_public_key)
	    		{
		    		$reason_message = __("Pleace specify Electrum Master Public Key (Launch your electrum wallet, select Preferences->Import/Export->Master Public Key->Show)", 'woocommerce');
		    		$valid = false;
		    	}
	    		else if (!preg_match ('/^[a-f0-9]{128}$/', $this->electrum_master_public_key))
	    		{
		    		$reason_message = __("Electrum Master Public Key is invalid. Must be 128 characters long, consisting of digits and letters: 'a b c d e f'", 'woocommerce');
		    		$valid = false;
		    	}
		    	else if (!extension_loaded('gmp') && !extension_loaded('bcmath'))
		    	{
		    		$reason_message = __("ERROR: neither 'bcmath' nor 'gmp' math extensions are loaded For Electrum wallet options to function. Contact your hosting company and ask them to enable either 'bcmath' or 'gmp' extensions. 'gmp' is preferred (much faster)! \nAlternatively you may choose another 'Bitcoin Service Provider' option.", 'woocommerce');
		    		$valid = false;
		    	}
	    	}

	    	if (!$valid)
	    	{
	    		if ($ret_reason_message !== NULL)
	    			$ret_reason_message = $reason_message;
	    		return false;
	    	}
	    	//----------------------------------

	    	//----------------------------------
	    	// NOTE: currenly this check is not performed.
				//  		Do not limit support with present list of currencies. This was originally created because exchange rate APIs did not support many, but today
				//			they do support many more currencies, hence this check is removed for now.

	    	// Validate currency
	   		// $currency_code            = get_woocommerce_currency();
	   		// $supported_currencies_arr = BWWC__get_settings ('supported_currencies_arr');

		   	// if ($currency_code != 'BTC' && !@in_array($currency_code, $supported_currencies_arr))
		   	// {
			  //  $reason_message = __("Store currency is set to unsupported value", 'woocommerce') . "('{$currency_code}'). " . __("Valid currencies: ", 'woocommerce') . implode ($supported_currencies_arr, ", ");
	    	// 	if ($ret_reason_message !== NULL)
	    	// 		$ret_reason_message = $reason_message;
			  // return false;
		   	// }

	     	return true;
	    	//----------------------------------
	    }
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Initialise Gateway Settings Form Fields
	     *
	     * @access public
	     * @return void
	     */
	    function init_form_fields()
	    {
		    // This defines the settings we want to show in the admin area.
		    // This allows user to customize payment gateway.
		    // Add as many as you see fit.
		    // See this for more form elements: http://wcdocs.woothemes.com/codex/extending/settings-api/

	    	//-----------------------------------
	    	// Assemble currency ticker.
	   		$store_currency_code = get_woocommerce_currency();
	   		if ($store_currency_code == 'BTC')
	   			$currency_code = 'USD';
	   		else
	   			$currency_code = $store_currency_code;

				$currency_ticker = BWWC__get_exchange_rate_per_bitcoin ($currency_code, 'getfirst', 'bestrate', true);
	    	//-----------------------------------

	    	//-----------------------------------
	    	// Payment instructions
	    	$payment_instructions = '
<table class="bwwc-payment-instructions-table" id="bwwc-payment-instructions-table">
  <tr class="bpit-table-row">
    <td colspan="2">' . __('Please send your bitcoin payment as follows:', 'woocommerce') . '</td>
  </tr>
  <tr class="bpit-table-row">
    <td style="vertical-align:middle;" class="bpit-td-name bpit-td-name-amount">
      ' . __('Amount', 'woocommerce') . ' (<strong>BTC</strong>):
    </td>
    <td class="bpit-td-value bpit-td-value-amount">
      <div style="border:1px solid #FCCA09;padding:2px 6px;margin:2px;background-color:#FCF8E3;border-radius:4px;color:#CC0000;font-weight: bold;font-size: 120%;">
      	{{{BITCOINS_AMOUNT}}}
      </div>
    </td>
  </tr>
  <tr class="bpit-table-row">
    <td style="vertical-align:middle;" class="bpit-td-name bpit-td-name-btcaddr">
      Address:
    </td>
    <td class="bpit-td-value bpit-td-value-btcaddr">
      <div style="border:1px solid #FCCA09;padding:2px 6px;margin:2px;background-color:#FCF8E3;border-radius:4px;color:#555;font-weight: bold;font-size: 120%;">
        {{{BITCOINS_ADDRESS}}}
      </div>
    </td>
  </tr>
  <tr class="bpit-table-row">
    <td style="vertical-align:middle;" class="bpit-td-name bpit-td-name-qr">
	    QR Code:
    </td>
    <td class="bpit-td-value bpit-td-value-qr">
      <div style="border:1px solid #FCCA09;padding:5px;margin:2px;background-color:#FCF8E3;border-radius:4px;">
        <a href="bitcoin://{{{BITCOINS_ADDRESS}}}?amount={{{BITCOINS_AMOUNT}}}"><img src="https://blockchain.info/qr?data=bitcoin://{{{BITCOINS_ADDRESS}}}?amount={{{BITCOINS_AMOUNT}}}&size=180" style="vertical-align:middle;border:1px solid #888;" /></a>
      </div>
    </td>
  </tr>
</table>

' . __('Please note:', 'woocommerce') . '
<ol class="bpit-instructions">
    <li>' . __('You must make a payment within 1 hour, or your order will be cancelled', 'woocommerce') . '</li>
    <li>' . __('As soon as your payment is received in full you will receive email confirmation with order delivery details.', 'woocommerce') . '</li>
    <li>{{{EXTRA_INSTRUCTIONS}}}</li>
</ol>
';
				$payment_instructions = trim ($payment_instructions);

	    	$payment_instructions_description = '
						  <p class="description" style="width:50%;float:left;width:49%;">
					    	' . __( 'Specific instructions given to the customer to complete Bitcoins payment.<br />You may change it, but make sure these tags will be present: <b>{{{BITCOINS_AMOUNT}}}</b>, <b>{{{BITCOINS_ADDRESS}}}</b> and <b>{{{EXTRA_INSTRUCTIONS}}}</b> as these tags will be replaced with customer - specific payment details.', 'woocommerce' ) . '
						  </p>
						  <p class="description" style="width:50%;float:left;width:49%;">
					    	Payment Instructions, original template (for reference):<br />
					    	<textarea rows="2" onclick="this.focus();this.select()" readonly="readonly" style="width:100%;background-color:#f1f1f1;height:4em">' . $payment_instructions . '</textarea>
						  </p>
					';
				$payment_instructions_description = trim ($payment_instructions_description);
	    	//-----------------------------------

	    	$this->form_fields = array(
				'enabled' => array(
								'title' => __( 'Enable/Disable', 'woocommerce' ),
								'type' => 'checkbox',
								'label' => __( 'Enable Bitcoin Payments', 'woocommerce' ),
								'default' => 'yes'
							),
				'title' => array(
								'title' => __( 'Title', 'woocommerce' ),
								'type' => 'text',
								'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
								'default' => __( 'Bitcoin Payment', 'woocommerce' )
							),

				'service_provider' => array(
								'title' => __('Bitcoin service provider', 'woocommerce' ),
								'type' => 'select',
								'options' => array(
									''  => __( 'Please choose your provider', 'woocommerce' ),
									'electrum-wallet'  => __( 'Your own Electrum wallet', 'woocommerce' ),
									'blockchain.info' => __( 'Blockchain.info API', 'woocommerce' ),
									),
								'default' => '',
								'description' => $this->service_provider?__("Please select your Bitcoin service provider and press [Save changes]. Then fill-in necessary details and press [Save changes] again.<br />Recommended setting: <b>Your own Electrum wallet</b>", 'woocommerce'):__("Recommended setting: 'Your own Electrum wallet'. <a href='http://electrum.org/' target='_blank'>Free download of Electrum wallet here</a>.", 'woocommerce'),
							),

				'electrum_master_public_key' => array(
								'title' => __( 'Electrum wallet\'s Master Public Key', 'woocommerce' ),
								'type' => 'textarea',
								'default' => "",
								'css'     => $this->service_provider!='electrum-wallet'?'display:none;':'',
								'disabled' => $this->service_provider!='electrum-wallet'?true:false,
								'description' => $this->service_provider!='electrum-wallet'?__('Available when Bitcoin service provider is set to: <b>Your own Electrum wallet</b>.', 'woocommerce'):__('1. Launch <a href="http://electrum.org/" target="_blank">Electrum wallet</a> and get Master Public Key value from:<br />Wallet -> Master Public Key, or:<br />older version of Electrum: Preferences -> Import/Export -> Master Public Key -> Show.<br />Copy long number string and paste it in this field.<br />
									2. Change "gap limit" value to bigger value (to make sure youll see the total balance on your wallet):<br />
									Click on "Console" tab and run this command: <tt>wallet.storage.put(\'gap_limit\',100)</tt>
									<br />Then restart Electrum wallet to activate new gap limit. You may do it later at any time - gap limit does not affect functionlity of your online store.
									<br />If your online store receives lots of orders in bitcoins - you might need to set gap limit to even bigger value.
									', 'woocommerce'),
							),

				'bitcoin_addr_merchant' => array(
								'title' => __( 'Your personal bitcoin address', 'woocommerce' ),
								'type' => 'text',
								'css'     => $this->service_provider!='blockchain.info'?'display:none;':'',
								'disabled' => $this->service_provider!='blockchain.info'?true:false,
								'description' => $this->service_provider!='blockchain.info'?__('Available when Bitcoin service provider is set to: <b>Blockchain.info</b>', 'woocommerce'):__( 'Your own bitcoin address (such as: 1H9uAP3x439YvQDoKNGgSYCg3FmrYRzpD2) - where you would like the payment to be sent. When customer sends you payment for the product - it will be automatically forwarded to this address by blockchain.info APIs.', 'woocommerce' ),
								'default' => '',
							),


				'confirmations' => array(
								'title' => __( 'Number of confirmations required before accepting payment', 'woocommerce' ),
								'type' => 'text',
								'description' => __( 'After a transaction is broadcast to the Bitcoin network, it may be included in a block that is published to the network. When that happens it is said that one <a href="https://en.bitcoin.it/wiki/Confirmation" target="_blank">confirmation has occurred</a> for the transaction. With each subsequent block that is found, the number of confirmations is increased by one. To protect against double spending, a transaction should not be considered as confirmed until a certain number of blocks confirm, or verify that transaction. <br />6 is considered very safe number of confirmations, although it takes longer to confirm.', 'woocommerce' ),
								'default' => '6',
							),


				'exchange_rate_type' => array(
								'title' => __('Exchange rate calculation type', 'woocommerce' ),
								'type' => 'select',
								'disabled' => $store_currency_code=='BTC'?true:false,
								'options' => array(
									'vwap' => __( 'Weighted Average', 'woocommerce' ),
									'realtime' => __( 'Real time', 'woocommerce' ),
									'bestrate' => __( 'Most profitable', 'woocommerce' ),
									),
								'default' => 'vwap',
								'description' => ($store_currency_code=='BTC'?__('<span style="color:red;"><b>Disabled</b>: Applies only for stores with non-bitcoin default currency.</span><br />', 'woocommerce'):'') .
									__('<b>Weighted Average</b> (recommended): <a href="http://en.wikipedia.org/wiki/Volume-weighted_average_price" target="_blank">weighted average</a> rates polled from a number of exchange services<br />
										<b>Real time</b>: the most recent transaction rates polled from a number of exchange services.<br />
										<b>Most profitable</b>: pick better exchange rate of all indicators (most favorable for merchant). Calculated as: MIN (Weighted Average, Real time)') . '<br />' . $currency_ticker,
							),
				'exchange_multiplier' => array(
								'title' => __('Exchange rate multiplier', 'woocommerce' ),
								'type' => 'text',
								'disabled' => $store_currency_code=='BTC'?true:false,
								'description' => ($store_currency_code=='BTC'?__('<span style="color:red;"><b>Disabled</b>: Applies only for stores with non-bitcoin default currency.</span><br />', 'woocommerce'):'') .
									__('Extra multiplier to apply to convert store default currency to bitcoin price. <br />Example: <b>1.05</b> - will add extra 5% to the total price in bitcoins. May be useful to compensate merchant\'s loss to fees when converting bitcoins to local currency, or to encourage customer to use bitcoins for purchases (by setting multiplier to < 1.00 values).', 'woocommerce' ),
								'default' => '1.00',
							),
				'description' => array(
								'title' => __( 'Customer Message', 'woocommerce' ),
								'type' => 'text',
								'description' => __( 'Initial instructions for the customer at checkout screen', 'woocommerce' ),
								'default' => __( 'Please proceed to the next screen to see necessary payment details.', 'woocommerce' )
							),
				'instructions' => array(
								'title' => __( 'Payment Instructions (HTML)', 'woocommerce' ),
								'type' => 'textarea',
								'description' => $payment_instructions_description,
								'default' => $payment_instructions,
							),
				);
	    }
		//-------------------------------------------------------------------
/*
///!!!
									'<table>' .
									'	<tr><td colspan="2">' . __('Please send your bitcoin payment as follows:', 'woocommerce' ) . '</td></tr>' .
									'	<tr><td>Amount (฿): </td><td><div style="border:1px solid #CCC;padding:2px 6px;margin:2px;background-color:#FEFEF0;border-radius:4px;color:#CC0000;">{{{BITCOINS_AMOUNT}}}</div></td></tr>' .
									'	<tr><td>Address: </td><td><div style="border:1px solid #CCC;padding:2px 6px;margin:2px;background-color:#FEFEF0;border-radius:4px;color:blue;">{{{BITCOINS_ADDRESS}}}</div></td></tr>' .
									'</table>' .
									__('Please note:', 'woocommerce' ) .
									'<ol>' .
									'   <li>' . __('You must make a payment within 8 hours, or your order will be cancelled', 'woocommerce' ) . '</li>' .
									'   <li>' . __('As soon as your payment is received in full you will receive email confirmation with order delivery details.', 'woocommerce' ) . '</li>' .
									'   <li>{{{EXTRA_INSTRUCTIONS}}}</li>' .
									'</ol>'

*/

		//-------------------------------------------------------------------
		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 *
		 * @access public
		 * @return void
		 */
		public function admin_options()
		{
			$validation_msg = "";
			$store_valid    = $this->BWWC__is_gateway_valid_for_use ($validation_msg);

			// After defining the options, we need to display them too; thats where this next function comes into play:
	    	?>
	    	<h3><?php _e('Bitcoin Payment', 'woocommerce'); ?></h3>
	    	<p>
	    		<?php _e('Allows to accept payments in bitcoin. <a href="https://en.bitcoin.it/wiki/Main_Page" target="_blank">Bitcoins</a> are peer-to-peer, decentralized digital currency that enables instant payments from anyone to anyone, anywhere in the world
<p style="border:1px solid #890e4e;padding:5px 10px;color:#004400;background-color:#FFF;"><u>Please donate BTC to</u>:&nbsp;&nbsp;<span style="color:#d21577;font-size:110%;font-weight:bold;">12fFTMkeu3mcunCtGHtWb7o5BcWA9eFx7R</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u>or via Paypal to</u>:&nbsp;&nbsp;<span style="color:#d21577;font-size:110%;font-weight:bold;">donate@bitcoinway.com</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-size:95%;">(All supporters will be acknowledged and listed within plugin repository)</span></p>
	    			',
	    				'woocommerce'); ?>
	    	</p>
	    	<?php
	    		echo $store_valid ? ('<p style="border:1px solid #DDD;padding:5px 10px;font-weight:bold;color:#004400;background-color:#CCFFCC;">' . __('Bitcoin payment gateway is operational','woocommerce') . '</p>') : ('<p style="border:1px solid #DDD;padding:5px 10px;font-weight:bold;color:#EE0000;background-color:#FFFFAA;">' . __('Bitcoin payment gateway is not operational: ','woocommerce') . $validation_msg . '</p>');
	    	?>
	    	<table class="form-table">
	    	<?php
	    		// Generate the HTML For the settings form.
	    		$this->generate_settings_html();
	    	?>
			</table><!--/.form-table-->
	    	<?php
	    }
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	  // Hook into admin options saving.
    public function process_admin_options()
    {
    	// Call parent
    	parent::process_admin_options();

    	if (isset($_POST) && is_array($_POST))
    	{
	  		$bwwc_settings = BWWC__get_settings ();
	  		if (!isset($bwwc_settings['gateway_settings']) || !is_array($bwwc_settings['gateway_settings']))
	  			$bwwc_settings['gateway_settings'] = array();

	    	$prefix        = 'woocommerce_bitcoin_';
	    	$prefix_length = strlen($prefix);

	    	foreach ($_POST as $varname => $varvalue)
	    	{
	    		if (strpos($varname, 'woocommerce_bitcoin_') === 0)
	    		{
	    			$trimmed_varname = substr($varname, $prefix_length);
	    			if ($trimmed_varname != 'description' && $trimmed_varname != 'instructions')
	    				$bwwc_settings['gateway_settings'][$trimmed_varname] = $varvalue;
	    		}
	    	}

	  		// Update gateway settings within BWWC own settings for easier access.
	      BWWC__update_settings ($bwwc_settings);
	    }
    }
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Process the payment and return the result
	     *
	     * @access public
	     * @param int $order_id
	     * @return array
	     */
		function process_payment ($order_id)
		{
			$order = new WC_Order ($order_id);

			//-----------------------------------
			// Save bitcoin payment info together with the order.
			// Note: this code must be on top here, as other filters will be called from here and will use these values ...
			//
			// Calculate realtime bitcoin price (if exchange is necessary)

			$exchange_rate = BWWC__get_exchange_rate_per_bitcoin (get_woocommerce_currency(), 'getfirst', $this->exchange_rate_type);
			/// $exchange_rate = BWWC__get_exchange_rate_per_bitcoin (get_woocommerce_currency(), $this->exchange_rate_retrieval_method, $this->exchange_rate_type);
			if (!$exchange_rate)
			{
				$msg = 'ERROR: Cannot determine Bitcoin exchange rate. Possible issues: store server does not allow outgoing connections, exchange rate servers are blocking incoming connections or down. ' .
					   'You may avoid that by setting store currency directly to Bitcoin(BTC)';
      			BWWC__log_event (__FILE__, __LINE__, $msg);
      			exit ('<h2 style="color:red;">' . $msg . '</h2>');
			}

			$order_total_in_btc   = ($order->get_total() / $exchange_rate);
			if (get_woocommerce_currency() != 'BTC')
				// Apply exchange rate multiplier only for stores with non-bitcoin default currency.
				$order_total_in_btc = $order_total_in_btc * $this->exchange_multiplier;

			$order_total_in_btc   = sprintf ("%.8f", $order_total_in_btc);

  		$bitcoins_address = false;

  		$order_info =
  			array (
  				'order_id'				=> $order_id,
  				'order_total'			=> $order_total_in_btc,
  				'order_datetime'  => date('Y-m-d H:i:s T'),
  				'requested_by_ip'	=> @$_SERVER['REMOTE_ADDR'],
  				);

  		$ret_info_array = array();

			if ($this->service_provider == 'blockchain.info')
			{
				$bitcoin_addr_merchant = $this->bitcoin_addr_merchant;
				$secret_key = substr(md5(microtime()), 0, 16);	# Generate secret key to be validate upon receiving IPN callback to prevent spoofing.
				$callback_url = trailingslashit (home_url()) . "?wc-api=BWWC_Bitcoin&secret_key={$secret_key}&bitcoinway=1&src=bcinfo&order_id={$order_id}"; // http://www.example.com/?bitcoinway=1&order_id=74&src=bcinfo
	   		BWWC__log_event (__FILE__, __LINE__, "Calling BWWC__generate_temporary_bitcoin_address__blockchain_info(). Payments to be forwarded to: '{$bitcoin_addr_merchant}' with callback URL: '{$callback_url}' ...");

	   			// This function generates temporary bitcoin address and schedules IPN callback at the same
				$ret_info_array = BWWC__generate_temporary_bitcoin_address__blockchain_info ($bitcoin_addr_merchant, $callback_url);
	
				/*
            $ret_info_array = array (
               'result'                      => 'success', // OR 'error'
               'message'										 => '...',
               'host_reply_raw'              => '......',
               'generated_bitcoin_address'   => '1H9uAP3x439YvQDoKNGgSYCg3FmrYRzpD2', // or false
               );
				*/
				$bitcoins_address = @$ret_info_array['generated_bitcoin_address'];
			}
			else if ($this->service_provider == 'electrum-wallet')
			{
				// Generate bitcoin address for electrum wallet provider.
				/*
            $ret_info_array = array (
               'result'                      => 'success', // OR 'error'
               'message'										 => '...',
               'host_reply_raw'              => '......',
               'generated_bitcoin_address'   => '1H9uAP3x439YvQDoKNGgSYCg3FmrYRzpD2', // or false
               );
				*/
				$ret_info_array = BWWC__get_bitcoin_address_for_payment__electrum ($this->electrum_master_public_key, $order_info);
				$bitcoins_address = @$ret_info_array['generated_bitcoin_address'];
			}

			if (!$bitcoins_address)
			{
				$msg = "ERROR: cannot generate bitcoin address for the order: '" . @$ret_info_array['message'] . "'";
      			BWWC__log_event (__FILE__, __LINE__, $msg);
      			exit ('<h2 style="color:red;">' . $msg . '</h2>');
			}

   		BWWC__log_event (__FILE__, __LINE__, "     Generated unique bitcoin address: '{$bitcoins_address}' for order_id " . $order_id);

			if ($this->service_provider == 'blockchain.info')
			{
	     	update_post_meta (
	     		$order_id, 			// post id ($order_id)
	     		'secret_key', 	// meta key
	     		$secret_key 		// meta value. If array - will be auto-serialized
	     		);
	 		}

     	update_post_meta (
     		$order_id, 			// post id ($order_id)
     		'order_total_in_btc', 	// meta key
     		$order_total_in_btc 	// meta value. If array - will be auto-serialized
     		);
     	update_post_meta (
     		$order_id, 			// post id ($order_id)
     		'bitcoins_address',	// meta key
     		$bitcoins_address 	// meta value. If array - will be auto-serialized
     		);
     	update_post_meta (
     		$order_id, 			// post id ($order_id)
     		'bitcoins_paid_total',	// meta key
     		"0" 	// meta value. If array - will be auto-serialized
     		);
     	update_post_meta (
     		$order_id, 			// post id ($order_id)
     		'bitcoins_refunded',	// meta key
     		"0" 	// meta value. If array - will be auto-serialized
     		);
     	update_post_meta (
     		$order_id, 				// post id ($order_id)
     		'_incoming_payments',	// meta key. Starts with '_' - hidden from UI.
     		array()					// array (array('datetime'=>'', 'from_addr'=>'', 'amount'=>''),)
     		);
     	update_post_meta (
     		$order_id, 				// post id ($order_id)
     		'_payment_completed',	// meta key. Starts with '_' - hidden from UI.
     		0					// array (array('datetime'=>'', 'from_addr'=>'', 'amount'=>''),)
     		);
			//-----------------------------------


			// The bitcoin gateway does not take payment immediately, but it does need to change the orders status to on-hold
			// (so the store owner knows that bitcoin payment is pending).
			// We also need to tell WooCommerce that it needs to redirect to the thankyou page – this is done with the returned array
			// and the result being a success.
			//
			global $woocommerce;

			//	Updating the order status:

			// Mark as on-hold (we're awaiting for bitcoins payment to arrive)
			$order->update_status('on-hold', __('Awaiting bitcoin payment to arrive', 'woocommerce'));

/*
			///////////////////////////////////////
			// timbowhite's suggestion:
			// -----------------------
			// Mark as pending (we're awaiting for bitcoins payment to arrive), not 'on-hold' since
      // woocommerce does not automatically cancel expired on-hold orders. Woocommerce handles holding the stock
      // for pending orders until order payment is complete.
			$order->update_status('pending', __('Awaiting bitcoin payment to arrive', 'woocommerce'));

			// Me: 'pending' does not trigger "Thank you" page and neither email sending. Not sure why.
			//			Also - I think cancellation of unpaid orders needs to be initiated from cron job, as only we know when order needs to be cancelled,
			//			by scanning "on-hold" orders through 'assigned_address_expires_in_mins' timeout check.
			///////////////////////////////////////
*/
			// Remove cart
			$woocommerce->cart->empty_cart();

			// Empty awaiting payment session
			unset($_SESSION['order_awaiting_payment']);

			// Return thankyou redirect
			if (version_compare (WOOCOMMERCE_VERSION, '2.1', '<'))
			{
				return array(
					'result' 	=> 'success',
					'redirect'	=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(woocommerce_get_page_id('thanks'))))
				);
			}
			else
			{
				return array(
						'result' 	=> 'success',
						'redirect'	=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, $this->get_return_url( $order )))
					);
			}
		}
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Output for the order received page.
	     *
	     * @access public
	     * @return void
	     */
		function BWWC__thankyou_page($order_id)
		{
			// BWWC__thankyou_page is hooked into the "thank you" page and in the simplest case can just echo’s the description.

			// Get order object.
			// http://wcdocs.woothemes.com/apidocs/class-WC_Order.html
			$order = new WC_Order($order_id);

			// Assemble detailed instructions.
			$order_total_in_btc   = get_post_meta($order->id, 'order_total_in_btc',   true); // set single to true to receive properly unserialized array
			$bitcoins_address = get_post_meta($order->id, 'bitcoins_address', true); // set single to true to receive properly unserialized array


			$instructions = $this->instructions;
			$instructions = str_replace ('{{{BITCOINS_AMOUNT}}}',  $order_total_in_btc, $instructions);
			$instructions = str_replace ('{{{BITCOINS_ADDRESS}}}', $bitcoins_address, 	$instructions);
			$instructions =
				str_replace (
					'{{{EXTRA_INSTRUCTIONS}}}',

					$this->instructions_multi_payment_str,
					$instructions
					);
            $order->add_order_note( __("Order instructions: price=&#3647;{$order_total_in_btc}, incoming account:{$bitcoins_address}", 'woocommerce'));

	        echo wpautop (wptexturize ($instructions));
		}
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
	    /**
	     * Add content to the WC emails.
	     *
	     * @access public
	     * @param WC_Order $order
	     * @param bool $sent_to_admin
	     * @return void
	     */
		function BWWC__email_instructions ($order, $sent_to_admin)
		{
	    	if ($sent_to_admin) return;
	    	if (!in_array($order->status, array('pending', 'on-hold'), true)) return;
	    	if ($order->payment_method !== 'bitcoin') return;

	    	// Assemble payment instructions for email
			$order_total_in_btc   = get_post_meta($order->id, 'order_total_in_btc',   true); // set single to true to receive properly unserialized array
			$bitcoins_address = get_post_meta($order->id, 'bitcoins_address', true); // set single to true to receive properly unserialized array


			$instructions = $this->instructions;
			$instructions = str_replace ('{{{BITCOINS_AMOUNT}}}',  $order_total_in_btc, 	$instructions);
			$instructions = str_replace ('{{{BITCOINS_ADDRESS}}}', $bitcoins_address, 	$instructions);
			$instructions =
				str_replace (
					'{{{EXTRA_INSTRUCTIONS}}}',

					$this->instructions_multi_payment_str,
					$instructions
					);

			echo wpautop (wptexturize ($instructions));
		}
		//-------------------------------------------------------------------

		//-------------------------------------------------------------------
		/**
		 * Check for Bitcoin-related IPN callabck
		 *
		 * @access public
		 * @return void
		 */
		function BWWC__maybe_bitcoin_ipn_callback ()
		{
			// If example.com/?bitcoinway=1 is present - it is callback URL.
			if (isset($_REQUEST['bitcoinway']) && $_REQUEST['bitcoinway'] == '1')
			{
     		BWWC__log_event (__FILE__, __LINE__, "BWWC__maybe_bitcoin_ipn_callback () called and 'bitcoinway=1' detected. REQUEST  =  " . serialize(@$_REQUEST));

				if (@$_GET['src'] != 'bcinfo')
				{
					$src = $_GET['src'];
					BWWC__log_event (__FILE__, __LINE__, "Warning: received IPN notification with 'src'= '{$src}', which is not matching expected: 'bcinfo'. Ignoring ...");
					exit();
				}

				// Processing IPN callback from blockchain.info ('bcinfo')


				$order_id = @$_GET['order_id'];

				$secret_key = get_post_meta($order_id, 'secret_key', true);
				$secret_key_sent = @$_GET['secret_key'];
				// Check the Request secret_key matches the original one (blockchain.info sends all params back)
				if ($secret_key_sent != $secret_key)
				{
     			BWWC__log_event (__FILE__, __LINE__, "Warning: secret_key does not match! secret_key sent: '{$secret_key_sent}'. Expected: '{$secret_key}'. Processing aborted.");
     			exit ('Invalid secret_key');
				}

				$confirmations = @$_GET['confirmations'];


				if ($confirmations >= $this->confirmations)
				{

					// The value of the payment received in satoshi (not including fees). Divide by 100000000 to get the value in BTC.
					$value_in_btc 		= @$_GET['value'] / 100000000;
					$txn_hash 			= @$_GET['transaction_hash'];
					$txn_confirmations 	= @$_GET['confirmations'];

					//---------------------------
					// Update incoming payments array stats
					$incoming_payments = get_post_meta($order_id, '_incoming_payments', true);
					$incoming_payments[$txn_hash] =
						array (
							'txn_value' 		=> $value_in_btc,
							'dest_address' 		=> @$_GET['address'],
							'confirmations' 	=> $txn_confirmations,
							'datetime'			=> date("Y-m-d, G:i:s T"),
							);

					update_post_meta ($order_id, '_incoming_payments', $incoming_payments);
					//---------------------------

					//---------------------------
					// Recalc total amount received for this order by adding totals from uniquely hashed txn's ...
					$paid_total_so_far = 0;
					foreach ($incoming_payments as $k => $txn_data)
						$paid_total_so_far += $txn_data['txn_value'];

					update_post_meta ($order_id, 'bitcoins_paid_total', $paid_total_so_far);
					//---------------------------

					$order_total_in_btc = get_post_meta($order_id, 'order_total_in_btc', true);
					if ($paid_total_so_far >= $order_total_in_btc)
					{
						BWWC__process_payment_completed_for_order ($order_id, false);
					}
					else
					{
     				BWWC__log_event (__FILE__, __LINE__, "NOTE: Payment received (for BTC {$value_in_btc}), but not enough yet to cover the required total. Will be waiting for more. Bitcoins: now/total received/needed = {$value_in_btc}/{$paid_total_so_far}/{$order_total_in_btc}");
					}

			    // Reply '*ok*' so no more notifications are sent
			    exit ('*ok*');
				}
				else
				{
					// Number of confirmations are not there yet... Skip it this time ...
			    // Don't print *ok* so the notification resent again on next confirmation
   				BWWC__log_event (__FILE__, __LINE__, "NOTE: Payment notification received (for BTC {$value_in_btc}), but number of confirmations is not enough yet. Confirmations received/required: {$confirmations}/{$this->confirmations}");
			    exit();
				}
			}
		}
		//-------------------------------------------------------------------
	}
	//=======================================================================


	//-----------------------------------------------------------------------
	// Hook into WooCommerce - add necessary hooks and filters
	add_filter ('woocommerce_payment_gateways', 	'BWWC__add_bitcoin_gateway' );

	// Disable unnecessary billing fields.
	/// Note: it affects whole store.
	/// add_filter ('woocommerce_checkout_fields' , 	'BWWC__woocommerce_checkout_fields' );

	add_filter ('woocommerce_currencies', 			'BWWC__add_btc_currency');
	add_filter ('woocommerce_currency_symbol', 		'BWWC__add_btc_currency_symbol', 10, 2);

	// Change [Order] button text on checkout screen.
    /// Note: this will affect all payment methods.
    /// add_filter ('woocommerce_order_button_text', 	'BWWC__order_button_text');
	//-----------------------------------------------------------------------

	//=======================================================================
	/**
	 * Add the gateway to WooCommerce
	 *
	 * @access public
	 * @param array $methods
	 * @package
	 * @return array
	 */
	function BWWC__add_bitcoin_gateway( $methods )
	{
		$methods[] = 'BWWC_Bitcoin';
		return $methods;
	}
	//=======================================================================

	//=======================================================================
	// Our hooked in function - $fields is passed via the filter!
	function BWWC__woocommerce_checkout_fields ($fields)
	{
	     unset($fields['order']['order_comments']);
	     unset($fields['billing']['billing_first_name']);
	     unset($fields['billing']['billing_last_name']);
	     unset($fields['billing']['billing_company']);
	     unset($fields['billing']['billing_address_1']);
	     unset($fields['billing']['billing_address_2']);
	     unset($fields['billing']['billing_city']);
	     unset($fields['billing']['billing_postcode']);
	     unset($fields['billing']['billing_country']);
	     unset($fields['billing']['billing_state']);
	     unset($fields['billing']['billing_phone']);
	     return $fields;
	}
	//=======================================================================

	//=======================================================================
	function BWWC__add_btc_currency($currencies)
	{
	     $currencies['BTC'] = __( 'Bitcoin (฿)', 'woocommerce' );
	     return $currencies;
	}
	//=======================================================================

	//=======================================================================
	function BWWC__add_btc_currency_symbol($currency_symbol, $currency)
	{
		switch( $currency )
		{
			case 'BTC':
				$currency_symbol = '฿';
				break;
		}

		return $currency_symbol;
	}
	//=======================================================================

	//=======================================================================
 	function BWWC__order_button_text () { return 'Continue'; }
	//=======================================================================
}
//###########################################################################

//===========================================================================
function BWWC__process_payment_completed_for_order ($order_id, $bitcoins_paid=false)
{

	if ($bitcoins_paid)
		update_post_meta ($order_id, 'bitcoins_paid_total', $bitcoins_paid);

	// Payment completed
	// Make sure this logic is done only once, in case customer keep sending payments :)
	if (!get_post_meta($order_id, '_payment_completed', true))
	{
		update_post_meta ($order_id, '_payment_completed', '1');

		BWWC__log_event (__FILE__, __LINE__, "Success: order '{$order_id}' paid in full. Processing and notifying customer ...");

		// Instantiate order object.
		$order = new WC_Order($order_id);
		$order->add_order_note( __('Order paid in full', 'woocommerce') );

	  $order->payment_complete();
	}
}
//===========================================================================
