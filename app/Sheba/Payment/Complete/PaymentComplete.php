<?php

namespace Sheba\Payment\Complete;


use App\Models\Payment;
use App\Repositories\PaymentRepository;

abstract class PaymentComplete
{
    /** @var Payment $partner_order_payment */
    protected $payment;

    /** @var PaymentRepository  */
    protected $paymentRepository;

    public function __construct()
    {
        $this->paymentRepository = app(PaymentRepository::class);
    }

    public function setPayment(Payment $payment)
    {
        $this->payment = $payment;
        $this->paymentRepository->setPayment($payment);
    }

    public abstract function complete();
}