<?php

namespace App\Sheba\Payment\Methods\AamarPay;

class AamarPayDynamicAuth
{
    private $storeId;
    private $signatureKey;

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

    public function getConfigurationsArray()
    {
        return [
            'storeId' => $this->storeId,
            'signatureKey' => $this->signatureKey
        ];
    }
}