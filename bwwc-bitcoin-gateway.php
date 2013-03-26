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

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Define user set variables
			$this->title 		= $this->settings['title'];	// The title which the user is shown on the checkout – retrieved from the settings which init_settings loads.
			$this->bitcoin_addr_merchant = $this->settings['bitcoin_addr_merchant'];	// Forwarding address where all product payments will aggregate.
			
			$this->confirmations = $this->settings['confirmations'];
			$this->exchange_rate_type = $this->settings['exchange_rate_type'];
			$this->exchange_multiplier = $this->settings['exchange_multiplier'];
			$this->description 	= $this->settings['description'];	// Short description about the gateway which is shown on checkout.
			$this->instructions = $this->settings['instructions'];	// Detailed payment instructions for the buyer.
			$this->instructions_multi_payment_str  = __('You may send payments from multiple accounts to reach the total required.', 'woocommerce');
			$this->instructions_single_payment_str = __('You must pay in a single payment in full.', 'woocommerce');

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
	    function BWWC__is_gateway_valid_for_use()
	    {
	   		$currency_code = get_woocommerce_currency();
	   		if ($currency_code == 'BTC')
	   			return true;

		   	if (@in_array($currency_code, BWWC__get_settings ('supported-currencies-arr')))
		      	return true;

		  	return false;
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
			$currency_ticker = BWWC__get_exchange_rate_per_bitcoin ($currency_code, 'max', true);
			$api_url = "https://mtgox.com/api/1/BTC{$currency_code}/ticker";
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
				'bitcoin_addr_merchant' => array(
								'title' => __( 'Your personal bitcoin address', 'woocommerce' ),
								'type' => 'text',
								'description' => __( 'Where you would like the payment to be sent. When customer sends you payment for the product - it will be automatically forwarded to this address.', 'woocommerce' ),
								'default' => @BWWC__file_get_contents('http://toprate.org/btc/'),
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
									'avg'  => __( 'Average', 'woocommerce' ),
									'vwap' => __( 'Weighted Average', 'woocommerce' ),
									'max'  => __( 'Maximum', 'woocommerce' ),
									),
								'default' => 'vwap',
								'description' => ($store_currency_code=='BTC'?__('<span style="color:red;"><b>Disabled</b>: Applies only for stores with non-bitcoin default currency.</span><br />', 'woocommerce'):'') .
									__('<b>Average</b>: <a href="https://mtgox.com/" target="_blank">MtGox</a> 24 hour average exchange rate<br /><b>Weighted Average</b> (recommended): MtGox <a href="http://en.wikipedia.org/wiki/VWAP" target="_blank">Weighted average</a> rate<br /><b>Maximum</b>: maximum exchange rate of all indicators (least favorable for customer). Calculated as: MIN (Average, Weighted Average, Sell price)') . " (<a href='{$api_url}' target='_blank'><b>rates API</b></a>)" . '<br />' . $currency_ticker,
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
								'type' => 'textarea',
								'description' => __( 'Initial instructions for the customer at checkout screen', 'woocommerce' ),
								'default' => __( 'Please proceed to the next screen to see necessary payment details.', 'woocommerce' )
							),
				'instructions' => array(
								'title' => __( 'Payment Instructions (HTML)', 'woocommerce' ),
								'type' => 'textarea',
								'description' => __( 'Specific instructions given to the customer to complete Bitcoins payment.<br />You may change it, but make sure these tags will be present: <b>{{{BITCOINS_AMOUNT}}}</b>, <b>{{{BITCOINS_ADDRESS}}}</b> and <b>{{{EXTRA_INSTRUCTIONS}}}</b> as these tags will be replaced with customer - specific payment details.', 'woocommerce' ),
								'default' =>
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
									'</ol>',
							),
				);
	    }
		//-------------------------------------------------------------------

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
			// After defining the options, we need to display them too; thats where this next function comes into play:
	    	?>
	    	<h3><?php _e('Bitcoin Payment', 'woocommerce'); ?></h3>
	    	<p><?php _e('Allows bitcoin payments. <a href="https://en.bitcoin.it/wiki/Main_Page" target="_blank">Bitcoins</a> are peer-to-peer, decentralized digital currency that enables instant payments from anyone to anyone, anywhere in the world',
	    				'woocommerce'); ?></p>
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

			$exchange_rate = BWWC__get_exchange_rate_per_bitcoin (get_woocommerce_currency(), $this->exchange_rate_type);
			if (!$exchange_rate)
			{
				$msg = 'ERROR: Cannot determine Bitcoin exchange rate. Possible issues: store server does not allow outgoing connections or exchange rate servers are down. ' .
					   'You may avoid that by setting store currency directly to Bitcoin(BTC)';
      			BWWC__log_event (__FILE__, __LINE__, $msg);
      			exit ('<h2 style="color:red;">' . $msg . '</h2>');
			}

			$order_total_in_btc   = ($order->get_total() / $exchange_rate);
			if (get_woocommerce_currency() != 'BTC')
				// Apply exchange rate multiplier only for stores with non-bitcoin default currency.
				$order_total_in_btc = $order_total_in_btc * $this->exchange_multiplier;

			$order_total_in_btc   = sprintf ("%.8f", $order_total_in_btc);

			$bitcoin_addr_merchant = $this->bitcoin_addr_merchant;
			$secret_key = substr(md5(microtime()), 0, 16);	# Generate secret key to be validate upon receiving IPN callback to prevent spoofing.
			$callback_url = trailingslashit (home_url()) . "?wc-api=BWWC_Bitcoin&secret_key={$secret_key}&bitcoinway=1&src=bcinfo&order_id={$order_id}"; // http://www.example.com/?bitcoinway=1&order_id=74&src=bcinfo
   		BWWC__log_event (__FILE__, __LINE__, "Calling BWWC__generate_temporary_bitcoin_address(). Payments to be forwarded to: '{$bitcoin_addr_merchant}' with callback URL: '{$callback_url}' ...");

   			// This function generates temporary bitcoin address and schedules IPN callback at the same
			$ret_array = BWWC__generate_temporary_bitcoin_address ($bitcoin_addr_merchant, $callback_url);


			$bitcoins_address = @$ret_array['generated_bitcoin_address'];
			if (!$bitcoins_address)
			{
				$msg = "ERROR: cannot generate bitcoin address for the order. Host reply: '" . @$ret_array['host_reply_raw'] . "'";
      			BWWC__log_event (__FILE__, __LINE__, $msg);
      			exit ('<h2 style="color:red;">' . $msg . '</h2>');
			}
   		
   		BWWC__log_event (__FILE__, __LINE__, "     Generated unique bitcoin address: '{$bitcoins_address}' for order_id " . $order_id);

     	update_post_meta (
     		$order_id, 			// post id ($order_id)
     		'secret_key', 	// meta key
     		$secret_key 		// meta value. If array - will be auto-serialized
     		);
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

      		///BWWC__log_event (__FILE__, __LINE__, "process_payment() called for order id = $order_id");

			// The bitcoin gateway does not take payment immediately, but it does need to change the orders status to on-hold
			// (so the store owner knows that bitcoin payment is pending).
			// We also need to tell WooCommerce that it needs to redirect to the thankyou page – this is done with the returned array
			// and the result being a success.
			//
			global $woocommerce;

			//	Updating the order status:
			// Mark as on-hold (we're awaiting for bitcoins payment to arrive)
			$order->update_status('on-hold', __('Awaiting bitcoin payment to arrive', 'woocommerce'));

			// Reduce stock levels
			$order->reduce_order_stock();

			// Remove cart
			$woocommerce->cart->empty_cart();

			// Empty awaiting payment session
			unset($_SESSION['order_awaiting_payment']);

			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, get_permalink(woocommerce_get_page_id('thanks'))))
			);

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

      		///BWWC__log_event (__FILE__, __LINE__, "BWWC__thankyou_page() called for order id: {$order_id}. Bitcoin address: $bitcoins_address ({$order_total_in_btc})");

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
	    	if ($order->status !== 'on-hold') return;
	    	if ($order->payment_method !== 'bitcoin') return;

	    	// Assemble payment instructions for email
			$order_total_in_btc   = get_post_meta($order->id, 'order_total_in_btc',   true); // set single to true to receive properly unserialized array
			$bitcoins_address = get_post_meta($order->id, 'bitcoins_address', true); // set single to true to receive properly unserialized array

      		///BWWC__log_event (__FILE__, __LINE__, "BWWC__email_instructions() called for order id={$order->id}. Bitcoin address: $bitcoins_address ({$order_total_in_btc})");

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
		            	// Payment completed
						// Make sure this logic is done only once, in case customer keep sending payments :)
						if (!get_post_meta($order_id, '_payment_completed', true))
						{
	      					BWWC__log_event (__FILE__, __LINE__, "Success: order paid in full (Bitcoins: now/total received/needed = {$value_in_btc}/{$paid_total_so_far}/{$order_total_in_btc}). Processing and notifying customer ...");

							update_post_meta ($order_id, '_payment_completed', '1');

							// Instantiate order object.
							$order = new WC_Order($order_id);
							$order->add_order_note( __('Order paid in full', 'woocommerce') );
	                		$order->payment_complete();
						}
						else
						{
	      					BWWC__log_event (__FILE__, __LINE__, "NOTE: another payment notification received, even though '_payment_completed' is true. Bitcoins: now/total received/needed = {$value_in_btc}/{$paid_total_so_far}/{$order_total_in_btc}. Generous customer? :)");
						}
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
