<?php

namespace App\Sheba\Payment\Methods\AamarPay\Stores;

use App\Sheba\Payment\Methods\AamarPay\AamarPayDynamicAuth;
use Sheba\Payment\Methods\DynamicStore;
use Sheba\ResellerPayment\EncryptionAndDecryption;

class AamarPayDynamicStore
{
    use DynamicStore;

    private $auth;

    public function setAuthFromConfig($config): AamarPayDynamicStore
    {
        $this->auth = (new AamarPayDynamicAuth())
            ->setSignatureKey($config->signatureKey ?? null)
            ->setStoreId($config->storeId ?? null)
            ->setApiKey($config->apiKey ?? null);
        return $this;
    }

    public function getAuth()
    {
        return $this->auth;
    }

    public function setAuthFromEncryptedConfig($config): self
    {
        $config = (new EncryptionAndDecryption())->setData($config)->getDecryptedData();
        $config = json_decode($config);
        return $this->setAuthFromConfig($config);
    }
}