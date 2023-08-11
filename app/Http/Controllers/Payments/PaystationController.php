<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Sheba\Payment\Exceptions\AlreadyCompletingPayment;
use Sheba\Payment\Exceptions\InvalidPaymentMethod;
use Sheba\Payment\Factory\PaymentStrategy;
use Sheba\Payment\PaymentManager;
use Sheba\Payment\Statuses;
use Throwable;

class PaystationController extends Controller
{
    /** @var PaymentManager */
    private $manager;

    public function __construct(PaymentManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * @throws InvalidPaymentMethod
     * @throws Throwable
     * @throws AlreadyCompletingPayment
     */
    public function validatePayment(Request $request)
    {
        $this->validate($request, [
            'invoice_number' => 'required',
            'trx_id' => 'required',
        ]);
        $payment = Payment::where('id', $request->invoice_number)->first();
        if (empty($payment)) return api_response($request, null, 404, ['message' => 'payment not found']);

        if (!$payment->isValid() || $payment->isComplete()) {
            return api_response($request, null, 402, ['message' => "Invalid or completed payment"]);
        }

        $payment = $this->validateAndUpdatePayment($payment, $request->trx_id);

        $redirect_url = $payment->status === Statuses::COMPLETED ?
            $payment->payable->success_url . '?invoice_id=' . $payment->transaction_id :
            $payment->payable->fail_url . '?invoice_id=' . $payment->transaction_id;
        return redirect()->to($redirect_url);
    }

    private function validateAndUpdatePayment(Payment $payment, $gateway_trx_id)
    {
        $payment->gateway_transaction_id = $gateway_trx_id;
        $payment->update();

        try {
            $this->manager
                ->setMethodName(PaymentStrategy::PAYSTATION)
                ->setPayment($payment)
                ->complete();
        } catch (AlreadyCompletingPayment $e) {
        }

        $payment->reload();

        return $payment;
    }
}
