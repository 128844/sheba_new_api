<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

use App\Http\Requests;
use Sheba\Payment\PaymentManager;

class AamarpayController extends Controller
{
    public function validatePayment(Request $request, PaymentManager $payment_manager)
    {
//        dd($request->all());
        $redirect_url = config('sheba.front_url');
        /** @var Payment $payment */
        $payment = Payment::where('transaction_id', $request->mer_txnid)->first();
        if ($payment) {
            $redirect_url = $payment->payable->success_url . '?invoice_id=' . $payment->transaction_id;
            $method = $payment->paymentDetails->last()->method;
            if ($payment->isValid() && !$payment->isComplete()) {
                $payment_manager->setMethodName($method)->setPayment($payment)->complete();
            }
        } else {
            throw new \Exception('Payment not found to validate.');
        }

        return redirect($redirect_url);
    }
}
