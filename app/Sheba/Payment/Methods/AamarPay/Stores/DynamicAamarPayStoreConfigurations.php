<?php

namespace App\Sheba\Payment\Methods\AamarPay\Stores;

use Sheba\Payment\Exceptions\InvalidStoreConfiguration;
use Sheba\ResellerPayment\EncryptionAndDecryption;

class DynamicAamarPayStoreConfigurations
{
    protected $configuration;

    private $storeId;
    private $signatureKey;
    private $apiKey;

    public function __construct($configuration = "")
    {
        $configuration = !empty($configuration) ? (new EncryptionAndDecryption())->setData($configuration)->getDecryptedData() : "";
        $this->configuration = json_decode($configuration);
        if (isset($this->configuration)) {
            foreach ($this->configuration as $key => $value) {
                $this->$key = $value;
            }
        }
    }

    public function getConfiguration()
    {
        return $this->configuration;
    }

    public function decryptAndSetConfigurations($encryptedConfigurations): self
    {
        if (empty($encryptedConfigurations)) {
            throw new InvalidStoreConfiguration('No configurations found');
        }
        $configurations = (new EncryptionAndDecryption())->setData($encryptedConfigurations)->getDecryptedData();
        $configurations = json_decode($configurations);
        $this->storeId = $configurations->storeId;
        $this->signatureKey = $configurations->signatureKey;
        $this->apiKey = $configurations->apiKey ?? null;
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

    public function getApiKey()
    {
        return $this->apiKey;
    }
}