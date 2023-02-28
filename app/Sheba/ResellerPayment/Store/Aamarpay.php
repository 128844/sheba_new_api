<?php

namespace Sheba\ResellerPayment\Store;

use App\Sheba\Payment\Methods\AamarPay\AamarPayDynamicAuth;
use App\Sheba\Payment\Methods\AamarPay\Stores\DynamicAamarPayStoreConfigurations;
use Sheba\Dal\GatewayAccount\Contract as PgwGatewayAccountRepo;
use Sheba\Payment\Exceptions\InvalidConfigurationException;
use Sheba\Payment\Exceptions\InvalidStoreConfiguration;
use Sheba\ResellerPayment\EncryptionAndDecryption;
use Sheba\ResellerPayment\Exceptions\ResellerPaymentException;
use Sheba\ResellerPayment\Exceptions\StoreAccountNotFoundException;
use Sheba\ResellerPayment\Statics\StoreConfigurationStatic;

class Aamarpay extends PaymentStore
{
    private $conn_data;

    /**
     * @return mixed
     */
    public function getConfiguration()
    {
        $data = (new StoreConfigurationStatic())->getStoreConfiguration($this->key);
        $storeAccount = $this->getStoreAccount();
        $storedConfiguration = $storeAccount ? $storeAccount->configuration : "";
        $dynamicAamarpayConfiguration = (new DynamicAamarPayStoreConfigurations($storedConfiguration))->getConfiguration();
        return $this->buildData($data, $dynamicAamarpayConfiguration);
    }

    public function buildData($static_data, $dynamic_configuration)
    {
        foreach ($static_data as &$data) {
            $field_name = $data["id"];
            if ($data["input_type"] === "password") {
                continue;
            }
            $data["data"] = $dynamic_configuration ? $dynamic_configuration->$field_name : "";
        }

        return $static_data;
    }

    /**
     * @return void
     * @throws InvalidConfigurationException
     * @throws InvalidStoreConfiguration
     */
    public function postConfiguration()
    {
        $data = $this->makeStoreAccountData();
        // $this->test();
        $storeAccount = $this->partner->pgwGatewayAccounts()->where("gateway_type_id", $this->gateway_id)->first();
        if (isset($storeAccount)) {
            $storeAccount->configuration = $data["configuration"];
            $storeAccount->save();
        } else {
            $pgw_store_repo = app()->make(PgwGatewayAccountRepo::class);
            $pgw_store_repo->create($data);
        }
    }

    private static function staticSslConfigurations(): array
    {
        return [];
    }

    public function makeAndGetConfigurationData(): array
    {
        $configuration = (array)$this->data;
        return (new AamarPayDynamicAuth())
            ->setConfiguration($configuration)
            ->buildFromConfiguration()
            ->toArray();
    }

    private function makeStoreAccountData(): array
    {
        $configuration = json_encode($this->makeAndGetConfigurationData());
        $this->conn_data = (new EncryptionAndDecryption())->setData($configuration)->getEncryptedData();

        return [
            "gateway_type"    => 'pgw',
            "gateway_type_id" => (int)$this->gateway_id,
            "user_id"         => $this->partner->id,
            "user_type"       => 'partner',
            "name"            => "dynamic_".$this->key,
            "configuration"   => $this->conn_data
        ];
    }

    /**
     * @return void
     * @throws InvalidConfigurationException
     * @throws InvalidStoreConfiguration
     */
    public function test()
    {
        /** @var \App\Sheba\Payment\Methods\AamarPay\AamarPay $method */
        $method = app(\App\Sheba\Payment\Methods\AamarPay\AamarPay::class);
        $storeCredentials = $this->makeAndGetConfigurationData();
        $method->setStoreId($storeCredentials['storeId'])
            ->setSignatureKey($storeCredentials['signatureKey'])
            ->setApiKey($storeCredentials['apiKey'])
            ->testInit();
        $method->testApiKey();
    }

    /**
     * @param $status
     * @return void
     * @throws ResellerPaymentException
     * @throws StoreAccountNotFoundException
     */
    public function account_status_update($status)
    {
        $storeAccount = $this->partner->pgwGatewayAccounts()->where("gateway_type_id", $this->gateway_id)->first();
        if (!$storeAccount) {
            throw new StoreAccountNotFoundException();
        }
        if ($status == $storeAccount->status) {
            throw new ResellerPaymentException("The account is already in this status");
        }
        $storeAccount->status = $status;
        $storeAccount->save();
    }
}