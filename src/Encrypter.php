<?php
namespace aLkRicha\Capitalist;

use phpseclib\Crypt\RSA;
use phpseclib\Math\BigInteger;

class Encrypter
{
	/** @var Crypt_RSA */
    private $rsa = null;

	/** @var string */
    private $modulus = null;

	/** @var string */
    private $exponent = null;

	public function __construct($in_modulus, $in_exponent)
    {
		$this->modulus = new Math_BigInteger($in_modulus, 16);
		$this->exponent = new Math_BigInteger($in_exponent, 16);

		$this->rsa = new Crypt_RSA();
		$this->rsa->loadKey(array('n' => $this->modulus, 'e' => $this->exponent));
		$this->rsa->setPublicKey();
		$this->rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
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

	public function str2hex( $str ) {
		$unpacked = unpack('H*', $str);
		return array_shift( $unpacked );
	}
}
