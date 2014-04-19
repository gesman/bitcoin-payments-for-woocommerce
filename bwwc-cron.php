<?php
/*
Bitcoin Payments for WooCommerce
http://www.bitcoinway.com/
*/


// Include everything
define('BWWC_MUST_LOAD_WP',  '1');
include (dirname(__FILE__) . '/bwwc-include-all.php');

// Cpanel-scheduled cron job call
if (@$_REQUEST['hardcron']=='1')
  BWWC_cron_job_worker (true);

//===========================================================================
// '$hardcron' == true if job is ran by Cpanel's cron job.

function BWWC_cron_job_worker ($hardcron=false)
{
  global $wpdb;


  $bwwc_settings = BWWC__get_settings ();

  if (@$bwwc_settings['gateway_settings']['service_provider'] != 'electrum-wallet')
  {
    return; // Only active electrum wallet as a service provider needs cron job
  }

  // status = "unused", "assigned", "used"
  $btc_addresses_table_name     = $wpdb->prefix . 'bwwc_btc_addresses';

  $funds_received_value_expires_in_secs = $bwwc_settings['funds_received_value_expires_in_mins'] * 60;
  $assigned_address_expires_in_secs     = $bwwc_settings['assigned_address_expires_in_mins'] * 60;
  $confirmations_required = $bwwc_settings['gateway_settings']['confirmations'];

  $clean_address = NULL;
  $current_time = time();

  // Search for completed orders (addresses that received full payments for their orders) ...

  // NULL == not found
  // Retrieve:
  //     'assigned'   - unexpired, with old balances (due for revalidation. Fresh balances and still 'assigned' means no [full] payment received yet)
  //     'revalidate' - all
  //        order results by most recently assigned
  $query =
    "SELECT * FROM `$btc_addresses_table_name`
      WHERE
      (
        (`status`='assigned' AND (('$current_time' - `assigned_at`) < '$assigned_address_expires_in_secs'))
        OR
        (`status`='revalidate')
      )
      AND (('$current_time' - `received_funds_checked_at`) > '$funds_received_value_expires_in_secs')
      ORDER BY `received_funds_checked_at` ASC;"; // Check the ones that haven't been checked for longest time
  $rows_for_balance_check = $wpdb->get_results ($query, ARRAY_A);

  if (is_array($rows_for_balance_check))
  	$count_rows_for_balance_check = count($rows_for_balance_check);
  else
  	$count_rows_for_balance_check = 0;


  if (is_array($rows_for_balance_check))
  {
  	$ran_cycles = 0;
  	foreach ($rows_for_balance_check as $row_for_balance_check)
  	{
  		$ran_cycles++;	// To limit number of cycles per soft cron job.

		  // Prepare 'address_meta' for use.
		  $address_meta    = BWWC_unserialize_address_meta (@$row_for_balance_check['address_meta']);
		  $last_order_info = @$address_meta['orders'][0];

		  $row_id       = $row_for_balance_check['id'];


		  // Retrieve current balance at address.
		  $balance_info_array = BWWC__getreceivedbyaddress_info ($row_for_balance_check['btc_address'], $confirmations_required, $bwwc_settings['blockchain_api_timeout_secs']);
		  if ($balance_info_array['result'] == 'success')
		  {
		    /*
		    $balance_info_array = array (
					'result'                      => 'success',
					'message'                     => "",
					'host_reply_raw'              => "",
					'balance'                     => $funds_received,
					);
		    */

        // Refresh 'received_funds_checked_at' field
        $current_time = time();
        $query =
          "UPDATE `$btc_addresses_table_name`
             SET
                `total_received_funds` = '{$balance_info_array['balance']}',
                `received_funds_checked_at`='$current_time'
            WHERE `id`='$row_id';";
        $ret_code = $wpdb->query ($query);

        if ($balance_info_array['balance'] > 0)
        {

          if ($row_for_balance_check['status'] == 'revalidate')
          {
            // Address with suddenly appeared balance. Check if that is matching to previously-placed [likely expired] order
            if (!$last_order_info || !@$last_order_info['order_id'] || !@$balance_info_array['balance'] || !@$last_order_info['order_total'])
            {
              // No proper metadata present. Mark this address as 'xused' (used by unknown entity outside of this application) and be done with it forever.
              $query =
                "UPDATE `$btc_addresses_table_name`
                   SET
                      `status` = 'xused'
                  WHERE `id`='$row_id';";
              $ret_code = $wpdb->query ($query);
              continue;
            }
            else
            {
              // Metadata for this address is present. Mark this address as 'assigned' and treat it like that further down...
              $query =
                "UPDATE `$btc_addresses_table_name`
                   SET
                      `status` = 'assigned'
                  WHERE `id`='$row_id';";
              $ret_code = $wpdb->query ($query);
            }
          }

          BWWC__log_event (__FILE__, __LINE__, "Cron job: NOTE: Detected non-zero balance at address: '{$row_for_balance_check['btc_address']}, order ID = '{$last_order_info['order_id']}'. Detected balance ='{$balance_info_array['balance']}'.");

          if ($balance_info_array['balance'] < $last_order_info['order_total'])
          {
            BWWC__log_event (__FILE__, __LINE__, "Cron job: NOTE: balance at address: '{$row_for_balance_check['btc_address']}' (BTC '{$balance_info_array['balance']}') is not yet sufficient to complete it's order (order ID = '{$last_order_info['order_id']}'). Total required: '{$last_order_info['order_total']}'. Will wait for more funds to arrive...");
          }
        }
        else
        {

        }

        // Note: to be perfectly safe against late-paid orders, we need to:
        //	Scan '$address_meta['orders']' for first UNPAID order that is exactly matching amount at address.

		    if ($balance_info_array['balance'] >= $last_order_info['order_total'])
		    {
		      // Process full payment event

		      /*
		      $address_meta =
		         array (
		            'orders' =>
		               array (
		                  // All orders placed on this address in reverse chronological order
		                  array (
		                     'order_id'     => $order_id,
		                     'order_total'  => $order_total_in_btc,
		                     'order_datetime'  => date('Y-m-d H:i:s T'),
		                     'requested_by_ip' => @$_SERVER['REMOTE_ADDR'],
		                  ),
		                  array (
		                     ...
		                  ),
		               ),
		            'other_meta_info' => array (...)
		         );
		      */

	        // Last order was fully paid! Complete it...
	        BWWC__log_event (__FILE__, __LINE__, "Cron job: NOTE: Full payment for order ID '{$last_order_info['order_id']}' detected at address: '{$row_for_balance_check['btc_address']}' (BTC '{$balance_info_array['balance']}'). Total was required for this order: '{$last_order_info['order_total']}'. Processing order ...");

	        // Update order' meta info
	        $address_meta['orders'][0]['paid'] = true;

	        // Process and complete the order within WooCommerce (send confirmation emails, etc...)
	        BWWC__process_payment_completed_for_order ($last_order_info['order_id'], $balance_info_array['balance']);

	        // Update address' record
	        $address_meta_serialized = BWWC_serialize_address_meta ($address_meta);

	        // Update DB - mark address as 'used'.
	        //
	        $current_time = time();

          // Note: `total_received_funds` and `received_funds_checked_at` are already updated above.
          //
	        $query =
	          "UPDATE `$btc_addresses_table_name`
	             SET
	                `status`='used',
	                `address_meta`='$address_meta_serialized'
	            WHERE `id`='$row_id';";
	        $ret_code = $wpdb->query ($query);
	        BWWC__log_event (__FILE__, __LINE__, "Cron job: SUCCESS: Order ID '{$last_order_info['order_id']}' successfully completed.");


// This is not needed here. Let it process as many orders as are paid for in the same loop.
// Maybe to be moved there --> //..// (to avoid soft-cron checking of balance of hundreds of addresses in a same loop)
//
// 	        //	Return here to avoid overloading too many processing needs to one random visitor.
// 	        //	Then it means no more than one order can be processed per 2.5 minutes (or whatever soft cron schedule is).
// 	        //	Hard cron is immune to this limitation.
// 	        if (!$hardcron && $ran_cycles >= $bwwc_settings['soft_cron_max_loops_per_run'])
// 	        {

// 	        	return;
// 	        }
		    }
		  }
		  else
		  {
		    BWWC__log_event (__FILE__, __LINE__, "Cron job: Warning: Cannot retrieve balance for address: '{$row_for_balance_check['btc_address']}: " . $balance_info_array['message']);
		  }
		  //..//
		}
	}

	// Process all 'revalidate' addresses here.
	// ...

  //-----------------------------------------------------
  // Pre-generate new bitcoin address for electrum wallet

  // Try to retrieve mpk from copy of settings.
  if ($hardcron)
  {
    $electrum_mpk = @$bwwc_settings['gateway_settings']['electrum_master_public_key'];

    if ($electrum_mpk && @$bwwc_settings['gateway_settings']['service_provider'] == 'electrum-wallet')
    {
      // Calculate number of unused addresses belonging to currently active electrum wallet

      $origin_id = 'electrum.mpk.' . md5($electrum_mpk);

      $current_time = time();
      $assigned_address_expires_in_secs     = $bwwc_settings['assigned_address_expires_in_mins'] * 60;

      if ($bwwc_settings['reuse_expired_addresses'])
        $reuse_expired_addresses_query_part = "OR (`status`='assigned' AND (('$current_time' - `assigned_at`) > '$assigned_address_expires_in_secs'))";
      else
        $reuse_expired_addresses_query_part = "";

      // Calculate total number of currently unused addresses in a system. Make sure there aren't too many.

      // NULL == not found
      // Retrieve:
      //     'unused'   - with fresh zero balances
      //     'assigned' - expired, with fresh zero balances (if 'reuse_expired_addresses' is true)
      //
      // Hence - any returned address will be clean to use.
      $query =
        "SELECT COUNT(*) as `total_unused_addresses` FROM `$btc_addresses_table_name`
           WHERE `origin_id`='$origin_id'
           AND `total_received_funds`='0'
           AND (`status`='unused' $reuse_expired_addresses_query_part)
           ";
      $total_unused_addresses = $wpdb->get_var ($query);


      if ($total_unused_addresses < $bwwc_settings['max_unused_addresses_buffer'])
      {
        BWWC__generate_new_bitcoin_address_for_electrum_wallet ($bwwc_settings, $electrum_mpk);
      }
    }
  }
  //-----------------------------------------------------

}
//===========================================================================
