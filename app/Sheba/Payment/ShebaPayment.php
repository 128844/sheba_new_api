<?php namespace Sheba\Payment;

use App\Models\Payable;
use App\Models\Payment;
use App\Sheba\Payment\Policy\PaymentInitiate;
use ReflectionException;
use Sheba\Payment\Exceptions\InitiateFailedException;
use Sheba\Payment\Factory\PaymentProcessor;
use Sheba\Payment\Methods\PaymentMethod;

class ShebaPayment
{
    /** @var PaymentMethod */
    private $method;


    /**
     * @param $enum
     * @return $this
     * @throws ReflectionException
     */
    public function setMethod($enum)
    {
        $this->method = (new PaymentProcessor($enum))->method();
        return $this;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->$name;
    }


    /**
     * @param $payable_type
     * @param $payable_type_id
     * @return bool true if can init
     * @throws InitiateFailedException otherwise
     */
    public function canInit($payable_type, $payable_type_id)
    {
        /** @var PaymentInitiate $payment_initiate */
        $payment_initiate = app(PaymentInitiate::class);
        return $payment_initiate->setPaymentMethod($this->method)->setPayableType($payable_type)->setPayableTypeId($payable_type_id)->canPossible();
    }


    /**
     * @param Payable $payable
     * @return Payment
     * @throws InitiateFailedException otherwise
     */
    public function init(Payable $payable)
    {
        if ($this->canInit($payable->type, $payable->type_id)) return $this->method->init($payable);
    }

    /**
     * @param Payment $payment
     * @return Payment
     */
    public function complete(Payment $payment)
    {
        $payment = $this->method->validate($payment);
        if ($payment->canComplete()) {
            /** @var Payable $payable */
            $payable = $payment->payable;
            $completion_class = $payable->getCompletionClass();
            $completion_class->setPayment($payment);
            $payment = $completion_class->complete();
        }
        return $payment;
    }
}