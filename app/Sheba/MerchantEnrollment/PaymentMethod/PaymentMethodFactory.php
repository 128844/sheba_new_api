<?php

namespace Sheba\MerchantEnrollment\PaymentMethod;

use Sheba\Dal\PgwStore\Model as PgwStore;
use Sheba\MerchantEnrollment\Statics\PaymentMethodStatics;
use Sheba\ResellerPayment\Exceptions\InvalidKeyException;

class PaymentMethodFactory
{
    /*** @var PgwStore */
    private $payment_gateway;
    private $partner;
    private $paymentMethodClassPath = "Sheba\\MerchantEnrollment\\PaymentMethod\\";

    /**
     * @param $payment_gateway
     * @return PaymentMethodFactory
     */
    public function setPaymentGateway($payment_gateway): PaymentMethodFactory
    {
        $this->payment_gateway = $payment_gateway;
        return $this;
    }

    /**
     * @param  mixed  $partner
     * @return PaymentMethodFactory
     */
    public function setPartner($partner): PaymentMethodFactory
    {
        $this->partner = $partner;
        return $this;
    }

    /**
     * @return PaymentMethod
     * @throws InvalidKeyException
     */
    public function get(): PaymentMethod
    {
        $class_map = PaymentMethodStatics::classMap();
        $key = $this->payment_gateway->key;
        if (isset($class_map[$key])) {
            $class = $class_map[$key];
            /** @var PaymentMethod $payment_method */
            $payment_method = app("$this->paymentMethodClassPath$class\\$class");
            return $payment_method->setPartner($this->partner)->setPaymentMethod($this->payment_gateway);
        }

        throw new InvalidKeyException();
    }
}