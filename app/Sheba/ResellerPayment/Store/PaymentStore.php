<?php

namespace Sheba\ResellerPayment\Store;

use Sheba\ResellerPayment\EncryptionAndDecryption;

abstract class PaymentStore
{
    protected $key;
    protected $partner;
    protected $data;
    protected $gateway_id;
    // protected $conn_data;

    /**
     * @param  mixed  $key
     * @return PaymentStore
     */
    public function setKey($key): PaymentStore
    {
        $this->key = $key;
        return $this;
    }

    /**
     * @param  mixed  $partner
     * @return PaymentStore
     */
    public function setPartner($partner): PaymentStore
    {
        $this->partner = $partner;
        return $this;
    }

    /**
     * @param  mixed  $data
     */
    public function setData($data): PaymentStore
    {
        $this->data = json_decode($data);
        return $this;
    }

    /**
     * @param  mixed  $gateway_id
     * @return PaymentStore
     */
    public function setGatewayId($gateway_id): PaymentStore
    {
        $this->gateway_id = $gateway_id;
        return $this;
    }

    protected function getStoreAccount()
    {
        if (isset($this->partner)) {
            return $this->partner->pgwGatewayAccounts()->join('pgw_stores', 'gateway_type_id', '=', 'pgw_stores.id')
                ->where('pgw_stores.key', $this->key)->first();
        }
    }

    public function getAndSetConfiguration($configuration): array
    {
        $this->conn_data = (new EncryptionAndDecryption())->setData($configuration)->getEncryptedData();
        return [
                "pgw_store_id"  => (int)$this->gateway_id,
                "user_id"       => $this->partner->id,
                // "user_type"     => strtolower(class_basename($this->partner)),
                "user_type"     => get_class($this->partner),
                "name"          => "dynamic_$this->key",
                "configuration" => $this->conn_data
            ] + (isset($this->data->status) ? ['status' => $this->data->status] : []);
    }

    public abstract function getConfiguration();

    public abstract function postConfiguration();

    public abstract function test();

    public abstract function account_status_update($status);

}