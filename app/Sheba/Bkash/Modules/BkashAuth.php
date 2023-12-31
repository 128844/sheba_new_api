<?php namespace Sheba\Bkash\Modules;

class BkashAuth
{
    private $appKey;
    private $appSecret;
    private $username;
    private $password;
    private $url;
    private $merchantNumber;

    public function setKey($key)
    {
        $this->appKey = $key;
        return $this;
    }

    public function setSecret($secret)
    {
        $this->appSecret = $secret;
        return $this;
    }

    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @param mixed $merchant_number
     * @return BkashAuth
     */
    public function setMerchantNumber($merchant_number)
    {
        $this->merchantNumber = $merchant_number;
        return $this;
    }

    public function __get($name)
    {
        return $this->$name;
    }
}