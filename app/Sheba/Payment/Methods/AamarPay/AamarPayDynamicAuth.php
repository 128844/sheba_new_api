<?php

namespace App\Sheba\Payment\Methods\AamarPay;

class AamarPayDynamicAuth
{
    private $storeId;
    private $signatureKey;
    private $apiKey;

    public function setStoreId($storeId)
    {
        $this->storeId = $storeId;
        return $this;
    }

    public function setSignatureKey($signatureKey)
    {
        $this->signatureKey = $signatureKey;
        return $this;
    }

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    public function getConfigurationsArray()
    {
        return [
            'storeId' => $this->storeId,
            'signatureKey' => $this->signatureKey,
            'apiKey' => $this->apiKey,
        ];
    }
}