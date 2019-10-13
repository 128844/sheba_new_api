<?php namespace Sheba\Payment\Complete;

use App\Models\Payment;
use App\Repositories\PaymentRepository;
use Sheba\Payment\Statuses;

abstract class PaymentComplete
{
    /** @var Payment $partner_order_payment */
    protected $payment;

    /** @var PaymentRepository */
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

    protected function failPayment()
    {
        $this->changePaymentStatus(Statuses::FAILED);
    }

    protected function changePaymentStatus($to_status)
    {
        $this->paymentRepository->changeStatus([
            'to' => $to_status, 'from' => $this->payment->status, 'transaction_details' => $this->payment->transaction_details
        ]);
        $this->payment->status = $to_status;
        $this->payment->update();
    }

    protected function completePayment()
    {
        $this->changePaymentStatus(Statuses::COMPLETED);
    }

    abstract protected function saveInvoice();

    abstract public function complete();
}