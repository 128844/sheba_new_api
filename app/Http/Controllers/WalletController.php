<?php namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PartnerOrder;
use App\Models\Payment;
use App\Repositories\PaymentRepository;
use App\Sheba\Payment\Rechargable;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sheba\ModificationFields;
use Sheba\Payment\Adapters\Payable\RechargeAdapter;
use Sheba\Payment\ShebaPayment;
use DB;
use Sheba\Reward\BonusCredit;
use Sheba\Transport\Exception\TransportException;

class WalletController extends Controller
{
    use ModificationFields;

    public function validatePayment(Request $request)
    {
        try {
            /** @var Payment $payment */
            $payment = Payment::where('transaction_id', $request->transaction_id)->valid()->first();
            $this->setModifier($payment->payable->user);
            if (!$payment) return api_response($request, null, 404); elseif ($payment->isComplete()) return api_response($request, 1, 200, ['message' => 'Payment completed']);
            elseif (!$payment->canComplete()) return api_response($request, null, 400, ['message' => 'Payment validation failed.']);
            $sheba_payment = new ShebaPayment('wallet');
            $payment = $sheba_payment->complete($payment);
            if ($payment->isComplete()) $message = 'Payment successfully completed'; elseif ($payment->isPassed()) $message = 'Your payment has been received but there was a system error. It will take some time to transaction your order. Call 16516 for support.';
            return api_response($request, null, 200, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function recharge(Request $request)
    {
        try {
            $this->validate($request, ['payment_method' => 'required|in:online,bkash', 'amount' => 'required|numeric|min:10|max:5000', 'user_id' => 'required', 'user_type' => 'required|in:customer', 'remember_token' => 'required']);
            $class_name = "App\\Models\\" . ucwords($request->user_type);
            $user = $class_name::where([['id', (int)$request->user_id], ['remember_token', $request->remember_token]])->first();
            if (!$user) return api_response($request, null, 404, ['message' => 'User Not found.']);
            $recharge_adapter = new RechargeAdapter($user, $request->amount);
            $payment = (new ShebaPayment($request->payment_method))->init($recharge_adapter->getPayable());
            return api_response($request, $payment, 200, ['link' => $payment['link'], 'payment' => $payment->getFormattedPayment()]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function purchase(Request $request, PaymentRepository $paymentRepository, BonusCredit $bonus_credit)
    {
        try {
            $this->validate($request, ['user_id' => 'sometimes', 'transaction_id' => 'required', 'user_type' => 'sometimes|in:customer,affiliate', 'remember_token' => 'sometimes',]);
            /** @var Payment $payment */
            $payment = Payment::where('transaction_id', $request->transaction_id)->valid()->first();
            if (!$payment) return api_response($request, null, 404); elseif ($payment->isFailed()) return api_response($request, null, 500, ['message' => 'Payment failed']);
            elseif ($payment->isPassed()) return api_response($request, null, 200);
            $user = $payment->payable->user;
            $sheba_credit = $user->shebaCredit();
            $paymentRepository->setPayment($payment);
            if ($sheba_credit == 0 && $sheba_credit < $payment->payable->amount) {
                $paymentRepository->changeStatus(['to' => 'validation_failed', 'from' => $payment->status, 'transaction_details' => $payment->transaction_details, 'log' => "Insufficient balance. Purchase Amount: " . $payment->payable->amount . " & Sheba Credit: $sheba_credit"]);
                $payment->status = 'validation_failed';
                $payment->update();
                return api_response($request, null, 400, ['message' => 'You don\'t have sufficient credit']);
            }
            try {
                $transaction = '';
                DB::transaction(function () use ($payment, $user, $bonus_credit, &$transaction) {
                    $model_name = $payment->payable->getPayableModel();
                    $spent_model = $model_name::find($payment->payable->type_id);
                    $remaining = $bonus_credit->setUser($user)->setSpentModel($spent_model)->deduct($payment->payable->amount);
                    if ($remaining > 0) {
                        if ($user->wallet < $remaining) {
                            $remaining = $user->wallet;
                            $payment_detail = $payment->paymentDetails->where('method', 'wallet')->first();
                            $payment_detail->amount = $remaining;
                            $payment_detail->update();
                        }
                        $user->debitWallet($remaining);
                        $this->setModifier($user);
                        $wallet_transaction_data = ['amount' => $remaining, 'type' => 'Debit', 'log' => "Service Purchase.", // . ($spent_model instanceof PartnerOrder) ? "ORDER ID: {$spent_model->code()}" : "",
                            'created_at' => Carbon::now()];
                        if ($user instanceof Customer) {
                            $wallet_transaction_data += ['event_type' => get_class($spent_model), 'event_id' => $spent_model->id];
                        }
                        $transaction = $user->walletTransaction($wallet_transaction_data);
                    }
                });
                $paymentRepository->changeStatus(['to' => 'validated', 'from' => $payment->status, 'transaction_details' => $payment->transaction_details]);
                $payment->status = 'validated';
                $payment->transaction_details = json_encode(array('payment_id' => $payment->id, 'transaction_id' => $transaction ? $transaction->id : null));
                $payment->update();
            } catch (QueryException $e) {
                $payment->status = 'failed';
                $payment->update();
                app('sentry')->captureException($e);
                return api_response($request, null, 500);
            }
            return api_response($request, $user, 200);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getFaqs(Request $request)
    {
        try {
            $faqs = [['question' => '1. What is Bonus Credit?', 'answer' => 'Bonus credit is a promotional credit which is given by Sheba.xyz to make service purchase at discounted price.'], ['question' => '2. How to get bonus credit?', 'answer' => 'You can get bonus credit by purchasing services for which bonus credit offer is running. '], ['question' => '3. When does bonus credit expire?', 'answer' => 'From bonus credit list you can check the validity of each bonus credit.'], ['question' => '4. Where is bonus credit applicable?', 'answer' => 'Bonus credit can be applied in any sort of service booking. You can pay the full or partial amount of the total bill by bonus credit. '], ['question' => '5. What is Voucher?', 'answer' => 'Voucher is a promotional offer to buy bonus credit which can be used in any sort of service purchase. Each voucher has its own validity.'], ['question' => '6. How can I purchase Voucher?', 'answer' => 'Sheba voucher can be purchased through any payment method available at payment screen.'], ['question' => '7. Is there any hidden charge in purchasing Sheba Voucher?', 'answer' => 'There is no hidden charge applicable.']];
            return api_response($request, $faqs, 200, ['faqs' => $faqs]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
}