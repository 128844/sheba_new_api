<?php

namespace App\Sheba\Payment\Methods\AamarPay;

use ReflectionClass;

class AamarPayDynamicAuth
{
    protected $storeId;
    protected $signatureKey;
    protected $apiKey;

    public $configuration;

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

    /**
     * @return mixed
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    public function getConfigurationsArray()
    {
        return [
            'storeId'      => $this->storeId,
            'signatureKey' => $this->signatureKey,
            'apiKey'       => $this->apiKey,
        ];
    }

    /**
     * @param  mixed  $configuration
     * @return AamarPayDynamicAuth
     */
    public function setConfiguration($configuration): AamarPayDynamicAuth
    {
        $this->configuration = $configuration;
        return $this;
    }

    /**
     * @return $this
     */
    public function buildFromConfiguration(): AamarPayDynamicAuth
    {
        foreach ($this->configuration as $key => $value) {
            if (!empty($value)) {
                $this->$key = $value;
            }
        }
        return $this;
    }

    public function toArray(): array
    {
        $reflection_class = new ReflectionClass($this);
        $data = [];
        foreach ($reflection_class->getProperties() as $item) {
            if (!$item->isProtected()) {
                continue;
            }
            $data[$item->name] = $this->{$item->name};
        }
        return $data;
    }
}