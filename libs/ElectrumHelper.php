<?php

// To fix problem with earlier PHP versions not supporting hex2bin
if ( !function_exists( 'hex2bin' ) )
{
    function hex2bin( $str )
    {
        $sbin = "";
        $len = strlen( $str );
        for ( $i = 0; $i < $len; $i += 2 )
        {
            $sbin .= pack( "H*", substr( $str, $i, 2 ) );
        }

        return $sbin;
    }
}

class ElectrumHelper
{
    const BITCOIN_HEADER_PUB = "0488b21e";
    const BIP32_PRIME = 0x80000000;
    const V1 = 1;
    const V2 = 2;

    public static function mpk_to_bc_address($mpk, $index, $version, $is_for_change = false) {
        if ($version == self::V1) {
            $pubkey = self::mpkV1_to_pubkey($mpk, $index);
        }
        elseif ($version == self::V2) {
            $pubkey = self::mpkV2_to_pubkey($mpk, $index, $is_for_change);
        }
        else {
            throw new ErrorException("Unknown Electrum version");
        }

        return self::pubkey_to_bc_address($pubkey);
    }

    public static function mpkV1_to_pubkey($mpk, $index) {
        if ($mpk[0] === 'x') {
            throw new ErrorException("mpkV2 sent to mpkV1 function");
        }

        $params = self::secp256k1_params();
        $curve = new CurveFp($params['p'], $params['a'], $params['b']);
        $gen = new Point($curve, $params['x'], $params['y'], $params['n']);

        if (USE_EXT == 'GMP') {
            $x = gmp_Utils::gmp_hexdec('0x' . substr($mpk, 0, 64));
            $y = gmp_Utils::gmp_hexdec('0x' . substr($mpk, 64, 64));
            $z = gmp_Utils::gmp_hexdec('0x' . hash('sha256', hash('sha256', $index . ':0:' . pack('H*', $mpk), TRUE)));

            $pt = Point::add(new Point($curve, $x, $y), Point::mul($z, $gen));

            $keystr = "\x04" . str_pad(gmp_Utils::gmp_dec2base($pt->getX(), 256), 32, "\x0", STR_PAD_LEFT) . str_pad(gmp_Utils::gmp_dec2base($pt->getY(), 256), 32, "\x0", STR_PAD_LEFT);
        }
        elseif (USE_EXT === 'BCMATH') {
            $x = bcmath_Utils::bchexdec('0x' . substr($mpk, 0, 64));
            $y = bcmath_Utils::bchexdec('0x' . substr($mpk, 64, 64));
            $z = bcmath_Utils::bchexdec('0x' . hash('sha256', hash('sha256', $index . ':0:' . pack('H*', $mpk), TRUE)));

            $pt = Point::add(new Point($curve, $x, $y), Point::mul($z, $gen));

            $keystr = "\x04" . str_pad(bcmath_Utils::dec2base($pt->getX(), 256), 32, "\x0", STR_PAD_LEFT) . str_pad(bcmath_Utils::dec2base($pt->getY(), 256), 32, "\x0", STR_PAD_LEFT);
        }
        else {
            throw new ErrorException("Unknown math module");
        }

        return bin2hex($keystr);
    }

    public static function mpkV2_to_pubkey($mpk, $index, $is_for_change = false) {
        /**
         * A simple check if versions were confused. mpkV2 always starts with xpub.
         * If something would change in the future, consider removing this check.
         */
        if ($mpk[0] !== 'x') {
            throw new ErrorException("mpkV1 sent to mpkV2 function");
        }

        list($code, $key) = self::deserialize_mpk($mpk);
        foreach (array(intval($is_for_change), $index) as $n) {
            list($key, $code) = self::ckd_mpk_check($key, $code, $n);
        }

        return bin2hex($key);
    }

    public static function ckd_mpk_check($key, $code, $n) {
        if (USE_EXT === 'GMP') {
            if (gmp_cmp(strval($n), '0') < 0) {
                throw new ErrorException("Negative index (master private key needed)");
            }

            if (gmp_cmp(strval($n), strval(self::BIP32_PRIME)) >= 0) {
                throw new ErrorException("Index is too big");
            }
        }
        elseif (USE_EXT === 'BCMATH') {
            if (bccomp(strval($n), '0') < 0) {
                throw new ErrorException("Negative index (master private key needed)");
            }

            if (bccomp(strval($n), strval(self::BIP32_PRIME)) >= 0) {
                throw new ErrorException("Index is too big");
            }
        }
        else {
            throw new ErrorException("Unknown math module");
        }

        return self::ckd_mpk($key, $code, hex2bin(self::int_to_hex_pad($n, 4)));
    }

    public static function ckd_mpk($key, $code, $n)
    {
        $keystr = hash_hmac("sha512", $key . $n, $code, true);

        $params = self::secp256k1_params();
        $curve = new CurveFp($params['p'], $params['a'], $params['b']);
        $gen = new Point($curve, $params['x'], $params['y'], $params['n']);

        if (USE_EXT === 'GMP') {
            $pubkey_point = Point::add(
                Point::mul(gmp_Utils::gmp_base2dec(substr($keystr, 0, 32), 256), $gen),
                self::ser_to_point($key, $curve, $gen)
            );
        }
        elseif (USE_EXT === 'BCMATH') {
            $pubkey_point = Point::add(
                Point::mul(bcmath_Utils::base2dec(substr($keystr, 0, 32), 256), $gen),
                self::ser_to_point($key, $curve, $gen)
            );
        }
        else {
            throw new ErrorException("Unknown math module");
        }

        $key_n = self::pkpoint_to_pubkey($pubkey_point, true);
        $code_n = substr($keystr, 32);

        return array($key_n, $code_n);
    }

    public static function pkpoint_to_pubkey(Point $point, $compressed = false)
    {
        if (USE_EXT === 'GMP') {
            if ($compressed) {
                if (gmp_strval(gmp_and($point->getY(), '1'))) {
                    $key = '03' . self::int_to_hex_pad($point->getX(), 32);
                }
                else {
                    $key = '02' . self::int_to_hex_pad($point->getX(), 32);
                }
            }
            else {
                $key = '04' . self::int_to_hex_pad($point->getX(), 32) . self::int_to_hex_pad($point->getY(), 32);
            }
        }
        elseif (USE_EXT === 'BCMATH') {
            if ($compressed) {
                if (bcmod($point->getY(), '2')) {
                    $key = '03' . self::int_to_hex_pad($point->getX(), 32);
                }
                else {
                    $key = '02' . self::int_to_hex_pad($point->getX(), 32);
                }
            }
            else {
                $key = '04' . self::int_to_hex_pad($point->getX(), 32) . self::int_to_hex_pad($point->getY(), 32);
            }
        }
        else {
            throw new ErrorException("Unknown math module");
        }

        return hex2bin($key);
    }

    public static function ser_to_point($key, CurveFp $curve, Point $gen)
    {
        $order = $gen->getOrder();

        if (!in_array($key[0], array("\x02", "\x03", "\x04"))) {
            throw new ErrorException("Wrong key's zero byte");
        }

        if (USE_EXT === 'GMP') {
            if ($key[0] === "\x04") {
                return new Point(
                    $curve,
                    gmp_Utils::gmp_base2dec(substr($key, 1, 32), 256),
                    gmp_Utils::gmp_base2dec(substr($key, 33), 256),
                    $order
                );
            }

            $Mx = gmp_Utils::gmp_base2dec(substr($key, 1), 256);
        }
        elseif (USE_EXT === 'BCMATH') {
            if ($key[0] === "\x04") {
                return new Point(
                    $curve,
                    bcmath_Utils::base2dec(substr($key, 1, 32), 256),
                    bcmath_Utils::base2dec(substr($key, 33), 256),
                    $order
                );
            }

            $Mx = bcmath_Utils::base2dec(substr($key, 1), 256);
        }
        else {
            throw new ErrorException("Unknown math module");
        }

        return new Point($curve, $Mx, self::ECC_YfromX($Mx, $curve, $key[0] === "\x03"), $order);
    }

    public static function ECC_YfromX($x, CurveFp $curve, $odd = true) {
        $p = $curve->getPrime();
        $a = $curve->getA();
        $b = $curve->getB();

        if (USE_EXT === 'GMP') {
            for ($offset = 0; $offset < 128; ++$offset) {
                $Mx = gmp_add($x, strval($offset));
                $My2 = gmp_add(gmp_add(gmp_powm($Mx, '3', $p), $a * gmp_powm($Mx, '2', $p)), gmp_mod($b, $p));
                $My = gmp_powm($My2, gmp_div_q(gmp_add($p, '1'), '4'), $p);

                if ($curve->contains($Mx, $My)) {
                    if ($odd == gmp_strval(gmp_mod($My, '2'))) {
                        return $My;
                    }

                    return gmp_sub($p, $My);
                }
            }
        }
        elseif (USE_EXT === 'BCMATH') {
            for ($offset = 0; $offset < 128; ++$offset) {
                $Mx = bcadd($x, strval($offset));
                $My2 = bcadd(bcadd(bcpowmod($Mx, '3', $p), $a * bcpowmod($Mx, '2', $p)), bcmod($b, $p));
                $My = bcpowmod($My2, bcdiv(bcadd($p, '1'), '4'), $p);

                if ($curve->contains($Mx, $My)) {
                    if ($odd == bcmod($My, '2')) {
                        return $My;
                    }

                    return bcsub($p, $My);
                }
            }
        }
        else {
            throw new ErrorException("Unknown math module");
        }
    }

    public static function deserialize_mpk($mpk) {
        $mpk = self::base58_decode_check($mpk);

        if (strlen($mpk) != 78) {
            throw new ErrorException("Invalid MPK length");
        }

        if (bin2hex(substr($mpk, 0, 4)) !== self::BITCOIN_HEADER_PUB) {
            throw new ErrorException("Wrong MPK header: only public bitcoin MPK allowed");
        }

        $code = substr($mpk, 13, 32);
        $key = substr($mpk, 45);

        return array($code, $key);
    }

    public static function base58_decode_check($input) {
        $output = self::base58_decode($input);
        $key = substr($output, 0, -4);
        $csum = substr($output, -4);

        $hash = self::hash_hash($key);
        $cs32 = substr($hash, 0, 4);

        if ($cs32 !== $csum) {
            return "";
        }
        else {
            return $key;
        }
    }

    public static function base58_decode($input) {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $base = '58';

        $num = "0";
        if (USE_EXT === 'GMP') {
            for ($i = strlen($input) - 1, $j = "1"; $i >= 0; $i--, $j = gmp_mul($j, $base)) {
                $num = gmp_add($num, gmp_mul($j, strval(strpos($alphabet, $input[$i]))));
            }

            return gmp_Utils::gmp_dec2base(gmp_strval($num), 256);
        }
        elseif (USE_EXT === 'BCMATH') {
            for ($i = strlen($input) - 1, $j = "1"; $i >= 0; $i--, $j = bcmul($j, $base)) {
                $num = bcadd($num, bcmul($j, strval(strpos($alphabet, $input[$i]))));
            }

            return bcmath_Utils::dec2base($num, 256);
        }
        else {
            throw new ErrorException("Unknown math module");
        }
    }

    public static function int_to_hex_pad($int, $pad) {
        if (USE_EXT === 'GMP') {
            $hex = gmp_Utils::gmp_dec2base($int, 16);
        }
        elseif (USE_EXT === 'BCMATH') {
            $hex = bcmath_Utils::dec2base($int, 16);
        }
        else {
            throw new ErrorException("Unknown math module");
        }

        $hex = str_pad("", 2 * $pad - strlen($hex), '0') . $hex;

        return $hex;
    }

    public static function hash_160($input) {
        return hash("ripemd160", hash("sha256", $input, true), true);
    }

    public static function hash_hash($input) {
        return hash("sha256", hash("sha256", $input, true), true);
    }

    public static function base58_encode($input) {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $base = '58';

        $encoded = '';
        if (USE_EXT === 'GMP') {
            $num = gmp_Utils::gmp_base2dec($input, 256);
            while (intval($num) >= $base) {
                $div = gmp_strval(gmp_div($num, $base));
                $mod = gmp_strval(gmp_mod($num, $base));
                $encoded = $alphabet[intval($mod)] . $encoded;
                $num = $div;
            }
        }
        elseif (USE_EXT === 'BCMATH') {
            $num = bcmath_Utils::base2dec($input, 256);
            while (intval($num) >= $base) {
                $div = bcdiv($num, $base);
                $mod = bcmod($num, $base);
                $encoded = $alphabet[intval($mod)] . $encoded;
                $num = $div;
            }
        }
        else {
            throw new ErrorException("Unknown math module");
        }

        $encoded = $alphabet[intval($num)] . $encoded;
        $pad = '';
        $n = 0;
        while ($input[$n++] === "\x0")
            $pad .= '1';

        return $pad . $encoded;
    }

    public static function pubkey_to_bc_address($pubkey) {
        return self::hash_160_to_bc_address(self::hash_160(hex2bin($pubkey)));
    }

    public static function hash_160_to_bc_address($h160, $addrtype = 0) {
        $vh160 = chr($addrtype) . $h160;
        $h = self::hash_hash($vh160);
        $addr = $vh160 . substr($h, 0, 4);

        return self::base58_encode($addr);
    }

    public static function secp256k1_params() {
        if (USE_EXT === 'GMP') {
            return array(
                'p' => gmp_Utils::gmp_hexdec('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F'),
                'a' => gmp_Utils::gmp_hexdec('0x0000000000000000000000000000000000000000000000000000000000000000'),
                'b' => gmp_Utils::gmp_hexdec('0x0000000000000000000000000000000000000000000000000000000000000007'),
                'n' => gmp_Utils::gmp_hexdec('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141'),
                'x' => gmp_Utils::gmp_hexdec("0x79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798"),
                'y' => gmp_Utils::gmp_hexdec("0x483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8")
            );
        }
        elseif (USE_EXT === 'BCMATH') {
            return array(
                'p' => bcmath_Utils::bchexdec('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F'),
                'a' => bcmath_Utils::bchexdec('0x0000000000000000000000000000000000000000000000000000000000000000'),
                'b' => bcmath_Utils::bchexdec('0x0000000000000000000000000000000000000000000000000000000000000007'),
                'n' => bcmath_Utils::bchexdec('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141'),
                'x' => bcmath_Utils::bchexdec("0x79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798"),
                'y' => bcmath_Utils::bchexdec("0x483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8")
            );
        }
        else {
            throw new ErrorException("Unknown math module");
        }
    }
}
