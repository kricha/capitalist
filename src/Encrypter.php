<?php

declare(strict_types=1);

/*
 * This file is part of capitalist.net api.
 * (с) 2015 Capitalist.
 */

namespace aLkRicha\Capitalist;

use phpseclib\Crypt\RSA;
use phpseclib\Math\BigInteger;

class Encrypter
{
    /** @var RSA */
    private $rsa = null;

    /** @var string */
    private $modulus = null;

    /** @var string */
    private $exponent = null;

    public function __construct($in_modulus, $in_exponent)
    {
        $this->modulus  = new BigInteger($in_modulus, 16);
        $this->exponent = new BigInteger($in_exponent, 16);

        $this->rsa = new RSA();
        $this->rsa->loadKey(['n' => $this->modulus, 'e' => $this->exponent]);
        $this->rsa->setPublicKey();
        $this->rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);
    }

    public function getPublicKey()
    {
        return $this->rsa->getPublicKey();
    }

    public function getModulus()
    {
        return $this->modulus;
    }

    public function getExponent()
    {
        return $this->exponent;
    }

    public function encrypt($plaintext)
    {
        return $this->str2hex($this->rsa->encrypt($plaintext));
    }

    public function str2hex($str)
    {
        $unpacked = unpack('H*', $str);

        return array_shift($unpacked);
    }
}
