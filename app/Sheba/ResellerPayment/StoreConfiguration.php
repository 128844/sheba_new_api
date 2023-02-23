<?php

namespace Sheba\ResellerPayment;

use App\Models\Partner;
use Sheba\ResellerPayment\Exceptions\InvalidKeyException;
use Sheba\ResellerPayment\Exceptions\StoreValidationException;
use Sheba\ResellerPayment\Statics\StoreConfigurationStatic;
use Sheba\ResellerPayment\Store\PaymentStore;
use Sheba\ResellerPayment\Store\StoreFactory;

class StoreConfiguration
{
    private $key;
    /**
     * @var Partner
     */
    private $partner;
    private $request_data;
    private $gateway_id;

    public function __construct()
    {
    }

    /**
     * @param  mixed  $key
     * @return StoreConfiguration
     */
    public function setKey($key): StoreConfiguration
    {
        $this->key = $key;
        return $this;
    }

    /**
     * @param  mixed  $partner
     * @return StoreConfiguration
     */
    public function setPartner($partner): StoreConfiguration
    {
        $this->partner = $partner;
        return $this;
    }

    /**
     * @return mixed
     * @throws Exceptions\InvalidKeyException
     */
    public function getConfiguration()
    {
        /** @var PaymentStore $store */
        $store = (new StoreFactory())->setKey($this->key)->get();
        return $store->setPartner($this->partner)->setKey($this->key)->getConfiguration();
    }

    /**
     * @return void
     * @throws StoreValidationException|Exceptions\InvalidKeyException
     */
    public function storeConfiguration()
    {
        $this->validate();
        /** @var PaymentStore $store */
        $store = (new StoreFactory())->setKey($this->key)->get();
        $store->setData($this->request_data)->setPartner($this->partner)->setGatewayId($this->gateway_id)->postConfiguration();
    }

    /**
     * @param  mixed  $request_data
     * @return StoreConfiguration
     */
    public function setRequestData($request_data): StoreConfiguration
    {
        $this->request_data = $request_data;
        return $this;
    }

    /**
     * @param  mixed  $gateway_id
     * @return StoreConfiguration
     */
    public function setGatewayId($gateway_id): StoreConfiguration
    {
        $this->gateway_id = $gateway_id;
        return $this;
    }

    /**
     * @return void
     * @throws StoreValidationException|InvalidKeyException
     */
    public function validate()
    {
        $static_data = (new StoreConfigurationStatic())->getStoreConfiguration($this->key);
        if (!isset($static_data)) {
            throw new InvalidKeyException();
        }
        $request = json_decode($this->request_data, 1);
        if (!isset($request) || !is_array($request)) {
            throw new StoreValidationException();
        }
        foreach ($static_data as $data) {
            if ($data["mandatory"]) {
                if (array_key_exists($data["id"], $request)) {
                    continue;
                }
                throw new StoreValidationException();
            }
        }
    }

    /**
     * @param $status
     * @return void
     * @throws InvalidKeyException
     */
    public function updatePaymentGatewayStatus($status)
    {
        /** @var PaymentStore $store */
        $store = (new StoreFactory())->setKey($this->key)->get();
        $store->setGatewayId($this->gateway_id)->setPartner($this->partner)->account_status_update($status);
    }

}