<?php namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sheba\Payment\ShebaPayment;
use Sheba\TopUp\Vendor\Internal\SslClient;

class SslController extends Controller
{
    public function validatePayment(Request $request)
    {
        $redirect_url = config('sheba.front_url');
        try {
            /*if (empty($request->headers->get('referer'))) {
                return api_response($request, null, 400);
            };*/
            /** @var Payment $payment */
            $payment = Payment::where('gateway_transaction_id', $request->tran_id)->valid()->first();
            if (!$payment) throw new \Exception('Payment not found to validate.');
            $redirect_url = $payment->payable->success_url . '?invoice_id=' . $request->tran_id;
            if (!$payment->isComplete()) (new ShebaPayment('online'))->complete($payment);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
        }
        return redirect($redirect_url);
    }

    public function validateTopUp(Request $request)
    {
        try {
            $this->validate($request, [
                'vr_guid' => 'required',
                'guid' => 'required',
            ]);
            $ssl = new SslClient();
            $response = $ssl->getRecharge($request->guid, $request->vr_guid);
            return api_response($request, $response, 200, ['data' => $response]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function checkBalance(Request $request)
    {
        try {
            $ssl = new SslClient();
            $response = $ssl->getBalance();
            return api_response($request, $response, 200, ['data' => $response]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
}