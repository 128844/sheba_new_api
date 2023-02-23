<?php

namespace Sheba\ResellerPayment\Store;

use Illuminate\Foundation\Application;
use Sheba\ResellerPayment\Exceptions\InvalidKeyException;
use Throwable;

class StoreFactory
{
    private $key;

    /**
     * @param mixed $key
     * @return StoreFactory
     */
    public function setKey($key): StoreFactory
    {
        $this->key = $key;
        return $this;
    }

    /**
     * @return Application|mixed
     * @throws InvalidKeyException
     */
    public function get()
    {
        try {
            $storeClassPath = "Sheba\\ResellerPayment\\Store\\";
            $class = "$storeClassPath" . ucfirst($this->key);
            return app($class);
        } catch (Throwable $exception) {
            throw new InvalidKeyException();
        }
    }
}