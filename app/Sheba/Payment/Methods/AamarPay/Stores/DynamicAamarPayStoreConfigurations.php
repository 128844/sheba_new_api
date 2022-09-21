<?php

namespace App\Sheba\Payment\Methods\AamarPay\Stores;

use Sheba\Payment\Exceptions\InvalidStoreConfiguration;
use Sheba\ResellerPayment\EncryptionAndDecryption;

class DynamicAamarPayStoreConfigurations
{
    private $storeId;
    private $signatureKey;

    public function decryptAndSetConfigurations($encryptedConfigurations): self
    {
        if (empty($encryptedConfigurations)) {
            throw new InvalidStoreConfiguration('No configurations found');
        }
        $configurations = (new EncryptionAndDecryption())->setData($encryptedConfigurations)->getDecryptedData();
        $configurations = json_decode($configurations);
        $this->storeId = $configurations->storeId;
        $this->signatureKey = $configurations->signatureKey;
        return $this;
    }

    public function getStoreId()
    {
        return $this->storeId;
    }

    public function getSignatureKey()
    {
        return $this->signatureKey;
    }
}