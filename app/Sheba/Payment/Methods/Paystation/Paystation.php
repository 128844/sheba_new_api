<?php

namespace Sheba\Payment\Methods\Paystation;

use App\Models\Payable;
use App\Models\Payment;
use Sheba\Payment\Methods\PaymentMethod;

class Paystation extends PaymentMethod
{
    const NAME = 'paystation';

    /** @var Service */
    private $paystation;

    public function __construct(Service $paystation)
    {
        parent::__construct();
        $this->paystation = $paystation;
    }

    /**
     * @param Payable $payable
     * @return Payment
     * @throws \Exception
     */
    public function init(Payable $payable): Payment
    {
        $payment = $this->createPayment($payable);
        $init_response = $this->paystation->setPayment($payment)->createPayment();
        $this->statusChanger->setPayment($payment);

        if ($init_response->hasSuccess()) {
            $success = $init_response->getSuccess();
            $payment->transaction_details = json_encode($success->details);
            $payment->gateway_transaction_id = $success->id;
            $payment->redirect_url = $success->redirect_url;
            $payment->update();
        } else {
            $this->statusChanger->changeToInitiationFailed($init_response->getErrorDetailsString());
        }

        return $payment;
    }

    /**
     * @param Payment $payment
     * @return Payment
     */
    public function validate(Payment $payment): Payment
    {
        $validation_response = $this->paystation->setPayment($payment)->retrieveTransaction();
        $this->statusChanger->setPayment($payment);
        if ($validation_response->hasSuccess()) {
            $payment = $this->statusChanger->changeToValidated($validation_response->getSuccessDetailsString());
        } else {
            $payment = $this->statusChanger->changeToValidationFailed($validation_response->getErrorDetailsString());
        }
        return $payment;
    }

    public function getMethodName()
    {
        return self::NAME;
    }
}
