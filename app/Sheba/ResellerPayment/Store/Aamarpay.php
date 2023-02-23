<?php

namespace Sheba\ResellerPayment\Store;

use App\Sheba\Payment\Methods\AamarPay\Stores\DynamicAamarPayStoreConfigurations;
use Sheba\Dal\GatewayAccount\Contract as GatewayAccountRepo;
use Sheba\Payment\Exceptions\InvalidConfigurationException;
use Sheba\Payment\Methods\Ssl\Stores\DynamicSslStoreConfiguration;
use Sheba\ResellerPayment\EncryptionAndDecryption;
use Sheba\ResellerPayment\Exceptions\ResellerPaymentException;
use Sheba\ResellerPayment\Exceptions\StoreAccountNotFoundException;
use Sheba\ResellerPayment\Statics\StoreConfigurationStatic;

class Aamarpay extends PaymentStore
{
    // private $conn_data;

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
     */
    public function postConfiguration()
    {
    }

    private static function staticSslConfigurations(): array
    {
        return [];
    }

    public function makeAndGetConfigurationData(): array
    {
    }

    private function makeStoreAccountData(): array
    {
    }

    /**
     * @return void
     * @throws InvalidConfigurationException
     */
    public function test()
    {
    }

    /**
     * @param $status
     * @return void
     * @throws ResellerPaymentException
     * @throws StoreAccountNotFoundException
     */
    public function account_status_update($status)
    {
    }

}