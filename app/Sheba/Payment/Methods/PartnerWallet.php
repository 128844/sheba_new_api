<?php namespace Sheba\Payment\Methods;

use App\Models\Payable;
use App\Models\Payment;
use App\Models\PaymentDetail;
use DB;

use Carbon\Carbon;

use Sheba\RequestIdentification;
use Sheba\ModificationFields;

class PartnerWallet extends PaymentMethod
{
    use ModificationFields;

    /**
     * @param Payable $payable
     * @return Payment
     */
    public function init(Payable $payable): Payment
    {
        $invoice = 'SHEBA_CREDIT_' . strtoupper($payable->readable_type) . '_' . $payable->type_id . '_' . randomString(10, 1, 1);
        # $user_bonus = $payable->user->shebaBonusCredit();
        $payment = new Payment();
        DB::transaction(function () use ($payment, $payable, $invoice/*, $user_bonus*/) {
            $payment->payable_id = $payable->id;
            $payment->transaction_id = $invoice;
            $payment->status = 'initiated';
            $payment->valid_till = Carbon::tomorrow();
            $this->setModifier($payable->user);
            $payment->fill((new RequestIdentification())->get());
            $this->withCreateModificationField($payment);
            $payment->save();

            $this->savePaymentDetail($payment, $payable->amount, 'wallet');

            /*$remaining = $user_bonus >= $payable->amount ? 0 : $payable->amount - $user_bonus;
            if ($remaining == 0) {
                $this->savePaymentDetail($payment, $payable->amount, 'bonus');
            } else {
                $this->savePaymentDetail($payment, $remaining, 'wallet');
                if ($user_bonus > 0) $this->savePaymentDetail($payment, $payable->amount - $remaining, 'bonus');
            }*/
        });

        return $payment;
    }

    /**
     * @param Payment $payment
     * @param $amount
     * @param $method
     */
    private function savePaymentDetail(Payment $payment, $amount, $method)
    {
        $payment_details = new PaymentDetail();
        $payment_details->payment_id = $payment->id;
        $payment_details->method = $method;
        $payment_details->amount = $amount;
        $this->setModifier($payment->payable->user);
        $payment_details->created_at = Carbon::now();
        $this->withCreateModificationField($payment_details);
        $payment_details->save();
    }

    /**
     * @param Payment $payment
     * @return Payment
     */
    public function validate(Payment $payment)
    {
        return $payment;
    }
}