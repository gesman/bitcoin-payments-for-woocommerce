<?php

// https://github.com/bkkcoins/misc
//
// Example using GenAddress as a cmd line utility
//
// requires phpecc library
// easy way is: git clone git://github.com/mdanter/phpecc.git
// in the directory where this code lives
// and then this loader below will take care of it.

// bcmath module in php seems to be very slow
// apparently the gmp module is much faster
// base2dec needs to be written for gmp as phpecc is missing it

//===========================================================================
function BWWC__MATH_generate_bitcoin_address_from_mpk_v1 ($master_public_key, $key_index)
{
    return ElectrumHelper::mpk_to_bc_address($master_public_key, $key_index, ElectrumHelper::V1);
}
//===========================================================================

//===========================================================================
function BWWC__MATH_generate_bitcoin_address_from_mpk_v2 ($master_public_key, $key_index, $is_for_change = false)
{
    return ElectrumHelper::mpk_to_bc_address($master_public_key, $key_index, ElectrumHelper::V2, $is_for_change);
}
//===========================================================================

//===========================================================================
function BWWC__MATH_generate_bitcoin_address_from_mpk ($master_public_key, $key_index, $is_for_change = false)
{
	if (USE_EXT != 'GMP' && USE_EXT != 'BCMATH')
  {
    BWWC__log_event (__FILE__, __LINE__, "Neither GMP nor BCMATH PHP math libraries are present. Aborting.");
		return false;
  }

  if (preg_match ('/^[a-f0-9]{128}$/', $master_public_key))
    return BWWC__MATH_generate_bitcoin_address_from_mpk_v1 ($master_public_key, $key_index);

  if (preg_match ('/^xpub[a-zA-Z0-9]{107}$/', $master_public_key))
    return BWWC__MATH_generate_bitcoin_address_from_mpk_v2 ($master_public_key, $key_index, $is_for_change);

  BWWC__log_event (__FILE__, __LINE__, "Invalid MPK passed: '$master_public_key'. Aborting.");
  return false;
}
//===========================================================================
