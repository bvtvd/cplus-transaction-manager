<?php

namespace Bvtvd;

use Exception;

class Rsa
{
    public $prikey;
    public $pubkey;

    public function __construct($priKey = null, $pubKey = null)
    {
        $this->prikey = $priKey;
        $this->pubkey = $pubKey;
    }

    public function setPriKey($priKey)
    {
        $this->prikey = $priKey;
    }

    public function setPubKey($pubKey)
    {
        $this->pubkey = $pubKey;
    }

    /**
         公钥用于对数据进行加密，私钥用于对数据进行解密。
         私钥用于对数据进行签名，公钥用于对签名进行验证
         * @access public
         * @param 签名算法
         * @param $data
         * @return string
         */
    public function sign($data, $signatureAlg = OPENSSL_ALGO_SHA256)
    {
        if(is_null($this->prikey)){
            throw new Exception('private key is requried!');
        }

        $res = openssl_get_privatekey($this->prikey);
        openssl_sign($data, $sign, $res, $signatureAlg);
        openssl_free_key($res);
        //base64编码
        $sign = base64_encode($sign);
        return $sign;
    }

    /**
         * @access public
         * @param 加密算法
         * @param $data
         * @return string
         */
    public function rsa($data)
    {
        if(is_null($this->prikey)){
            throw new Exception('private key is requried!');
        }

        $encryptData = "";
        $res = openssl_get_privatekey($this->prikey);
        $result = openssl_private_encrypt($data, $encryptData, $res, OPENSSL_PKCS1_PADDING);
        openssl_free_key($res);
        return base64_encode($encryptData);
    }
    /**
         * @access public
         * @param 解密算法
         * @param $data
         * @return string
         */
    public function decryptRSA($data)
    {
        if(is_null($this->pubkey)){
            throw new Exception('public key is requried!');
        }

        $decryptData = '';
        $res = openssl_pkey_get_public($this->pubkey);
        $result = openssl_public_decrypt(base64_decode($data), $decryptData, $res);
        return $decryptData;
    }

    /**
         * @access public
         * @param 验签
         * @param $data
         * @return json
         */
    public function verify($signValue, $sign, $signatureAlg = OPENSSL_ALGO_SHA256)
    {
        if(is_null($this->pubkey)){
            throw new Exception('public key is requried!');
        }
        $res = openssl_get_publickey($this->pubkey);
        $result = (bool) openssl_verify($signValue, base64_decode($sign), $res, $signatureAlg);
        openssl_free_key($res);
        return $result;
    }
}
