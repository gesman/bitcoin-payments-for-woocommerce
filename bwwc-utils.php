<?php
/*
Bitcoin Payments for WooCommerce
http://www.bitcoinway.com/
*/

//===========================================================================
function BWWC__log_event ($filename, $linenum, $message, $extra_text="")
{
   $log_filename   = dirname(__FILE__) . '/__log.php';
   $logfile_header = '/* =============== BitcoinWay LOG file =============== */' . "\r\n";
   $logfile_tail   = "\r\nEND";

   // Delete too long logfiles.
   //if (@file_exists ($log_filename) && filesize($log_filename)>1000000)
   //   unlink ($log_filename);

   $filename = basename ($filename);

   if (@file_exists ($log_filename))
      {
      // 'r+' non destructive R/W mode.
      $fhandle = @fopen ($log_filename, 'r+');
      if ($fhandle)
         @fseek ($fhandle, -strlen($logfile_tail), SEEK_END);
      }
   else
      {
      $fhandle = @fopen ($log_filename, 'w');
      if ($fhandle)
         @fwrite ($fhandle, $logfile_header);
      }

   if ($fhandle)
      {
      @fwrite ($fhandle, "\r\n// " . $_SERVER['REMOTE_ADDR'] . '(' . $_SERVER['REMOTE_PORT'] . ')' . ' -> ' . date("Y-m-d, G:i:s T") . "|" . BWWC_VERSION . "/" . BWWC_EDITION . "|$filename($linenum)|: " . $message . ($extra_text?"\r\n//    Extra Data: $extra_text":"") . $logfile_tail);
      @fclose ($fhandle);
      }
}
//===========================================================================

//===========================================================================
// Input:
// ------

//    $callback_url => IPN notification URL upon received payment at generated address.
//    $forwarding_bitcoin_address => Where all payments received at generated address should be ultimately forwarded to.
//
// Returns:
// --------
//    Success => array ('result' => 'success', 'host_reply_raw' => '...', 'generated_bitcoin_address' => '...')
//    Error   => array ('result' => 'error',   'host_reply_raw' => '...')
//

function BWWC__generate_temporary_bitcoin_address ($forwarding_bitcoin_address, $callback_url)
{
   //--------------------------------------------
   // Normalize inputs.
   $callback_url = urlencode(urldecode($callback_url));  // Make sure it is URL encoded.



   $blockchain_api_call = "https://blockchain.info/api/receive?method=create&address={$forwarding_bitcoin_address}&anonymous=false&callback={$callback_url}";
   BWWC__log_event (__FILE__, __LINE__, "Calling blockchain.info API: " . $blockchain_api_call);
   $result = @BWWC__file_get_contents ($blockchain_api_call, true);
   if ($result)
   {
      $json_obj = @json_decode(trim($result));
      if (is_object($json_obj))
      {
         $generated_bitcoin_address = @$json_obj->input_address;
         if (strlen($generated_bitcoin_address) > 20)
         {
            $ret_data = array (
               'result'                      => 'success',
               'host_reply_raw'              => $result,
               'generated_bitcoin_address'   => $generated_bitcoin_address,
               );
            return $ret_data;
         }
      }
   }

   $ret_data = array (
      'result'          => 'error',
      'host_reply_raw'  => $result
      );
   return $ret_data;
}
//===========================================================================

//===========================================================================
// Returns:
//    success: number of currency units (dollars, etc...) would take to convert to 1 bitcoin, ex: "15.32476".
//    failure: false
//
// $currency_code, one of: USD, AUD, CAD, CHF, CNY, DKK, EUR, GBP, HKD, JPY, NZD, PLN, RUB, SEK, SGD, THB
// $rate_type:
//    'avg'     -- 24 hrs average
//    'vwap'    -- weighted average as per: http://en.wikipedia.org/wiki/VWAP
//    'max'     -- maximize number of bitcoins to get for item priced in currency: == min (avg, vwap, sell)
//                 This is useful to ensure maximum bitcoin gain for stores priced in other currencies.
//                 Note: This is the least favorable exchange rate for the store customer.
// $get_ticker_string - true - ticker string of all exchange types for the given currency.

function BWWC__get_exchange_rate_per_bitcoin ($currency_code, $rate_type = 'vwap', $get_ticker_string=false)
{
   if ($currency_code == 'BTC')
      return "1.00";   // 1:1

   if (!@in_array($currency_code, BWWC__get_settings ('supported-currencies-arr')))
      return false;

   $avg = $vwap = $sell = 0;

   $main_url   = "https://mtgox.com/api/1/BTC{$currency_code}/ticker";
   $backup_url = "http://blockchain.info/ticker";

   $result = @BWWC__file_get_contents ($main_url);
   if ($result)
   {
      $json_obj = @json_decode(trim($result));
      if (is_object($json_obj))
      {
         if ($json_obj->result == 'success')
         {
            $avg  = @$json_obj->return->avg->value;
            $vwap = @$json_obj->return->vwap->value;
            $sell = @$json_obj->return->sell->value;
         }
      }
   }

   if (!$avg || !$vwap || !$sell)
   {
      BWWC__log_event (__FILE__, __LINE__, "WARNING: Cannot retrieve bitcoin exchange rates from {$main_url}. SSL/server issues? Will try backup URL ...");
      // Fallback to backup URL
      $result = @BWWC__file_get_contents ($backup_url);
      if ($result)
      {
         $json_obj = @json_decode(trim($result));
         if (is_object($json_obj))
         {
            $key  = "15m";
            $avg  = $vwap = $sell = @$json_obj->$currency_code->$key;
         }
      }
   }

   if (!$avg || !$vwap || !$sell)
   {
      $msg = "<span style='color:red;'>WARNING: failed to retrieve bitcoin exchange rates from all attempts. Internet connection/outgoing call security issues?</span>";
      BWWC__log_event (__FILE__, __LINE__, $msg);
      if ($get_ticker_string)
         return $msg;
      else
         return false;
   }

   if ($get_ticker_string)
   {
      $max = min ($avg, $vwap, $sell);
      return "<span style='color:darkgreen;'>Current Rates for 1 Bitcoin (in {$currency_code}): Average={$avg}, Weighted Average={$vwap}, Maximum={$max}</span>";
   }

   switch ($rate_type)
      {
         case 'avg'  :  return $avg;
         case 'max'  :  return min ($avg, $vwap, $sell);
         case 'vwap' :
         default     :
                        return $vwap;
      }

}
//===========================================================================

//===========================================================================
/*
  Get web page contents with the help of PHP cURL library
   Success => content
   Error   => if ($return_content_on_error == true) $content; else FALSE;
*/
function BWWC__file_get_contents ($url, $return_content_on_error=false, $user_agent=FALSE)
{
   if (!function_exists('curl_init'))
      {
      return @file_get_contents ($url);
      }

   $options = array(
      CURLOPT_URL            => $url,
      CURLOPT_RETURNTRANSFER => true,     // return web page
      CURLOPT_HEADER         => false,    // don't return headers
//      CURLOPT_FOLLOWLOCATION => true,     // follow redirects
      CURLOPT_ENCODING       => "",       // handle compressed
      CURLOPT_USERAGENT      => $user_agent?$user_agent:'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.2; .NET CLR 1.1.4322)', // who am i
      CURLOPT_AUTOREFERER    => true,     // set referer on redirect
      CURLOPT_CONNECTTIMEOUT => 60,       // timeout on connect
      CURLOPT_TIMEOUT        => 60,       // timeout on response in seconds.
      CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
      );

   $ch      = curl_init   ();

   if (function_exists('curl_setopt_array'))
      {
      curl_setopt_array      ($ch, $options);
      }
   else
      {
      // To accomodate older PHP 5.0.x systems
      curl_setopt ($ch, CURLOPT_URL            , $url);
      curl_setopt ($ch, CURLOPT_RETURNTRANSFER , true);     // return web page
      curl_setopt ($ch, CURLOPT_HEADER         , false);    // don't return headers
      curl_setopt ($ch, CURLOPT_ENCODING       , "");       // handle compressed
      curl_setopt ($ch, CURLOPT_USERAGENT      , $user_agent?$user_agent:'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.2; .NET CLR 1.1.4322)'); // who am i
      curl_setopt ($ch, CURLOPT_AUTOREFERER    , true);     // set referer on redirect
      curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT , 60);       // timeout on connect
      curl_setopt ($ch, CURLOPT_TIMEOUT        , 60);       // timeout on response in seconds.
      curl_setopt ($ch, CURLOPT_MAXREDIRS      , 10);       // stop after 10 redirects
      }

   $content = curl_exec   ($ch);
   $err     = curl_errno  ($ch);
   $header  = curl_getinfo($ch);
   // $errmsg  = curl_error  ($ch);

   curl_close             ($ch);

   if (!$err && $header['http_code']==200)
      return $content;
   else
   {
      if ($return_content_on_error)
         return $content;
      else
         return FALSE;
   }
}
//===========================================================================
