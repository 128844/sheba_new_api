<?php namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\Customer;
use App\Models\PartnerOrder;
use App\Models\Payment;
use App\Repositories\PartnerRepository;
use App\Repositories\PaymentStatusChangeLogRepository;
use DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sheba\Authentication\Exceptions\AuthenticationFailedException;
use Sheba\FraudDetection\TransactionSources;
use Sheba\ModificationFields;
use Sheba\PartnerStatusAuthentication;
use Sheba\Payment\Adapters\Payable\RechargeAdapter;
use Sheba\Payment\AvailableMethods;
use Sheba\Payment\Exceptions\FailedToInitiate;
use Sheba\Payment\Exceptions\InitiateFailedException;
use Sheba\Payment\Exceptions\InvalidPaymentMethod;
use Sheba\Payment\Factory\PaymentStrategy;
use Sheba\Payment\PaymentManager;
use Sheba\Reward\BonusCredit;
use Sheba\Transactions\Types;
use Sheba\Transactions\Wallet\TransactionGateways;
use Sheba\Transactions\Wallet\WalletTransactionHandler;
use Throwable;

class WalletController extends Controller
{
    use ModificationFields;

    /**
     * @param Request $request
     * @param PaymentManager $payment_manager
     * @return JsonResponse
     */
    public function validatePayment(Request $request, PaymentManager $payment_manager): JsonResponse
    {
        try {
            /** @var Payment $payment */
            $payment = Payment::where('transaction_id', $request->transaction_id)->valid()->first();
            $this->setModifier($payment->payable->user);
            if (!$payment) return api_response($request, null, 404); elseif ($payment->isComplete()) return api_response($request, 1, 200, ['message' => 'Payment completed']);
            elseif (!$payment->canComplete()) return api_response($request, null, 400, ['message' => 'Payment validation failed.']);
            $payment = $payment_manager->setMethodName(PaymentStrategy::WALLET)->setPayment($payment)->complete();
            if ($payment->isComplete()) $message = 'Payment successfully completed'; elseif ($payment->isPassed()) $message = 'Your payment has been received but there was a system error. It will take some time to transaction your order. Call 16516 for support.';
            return api_response($request, null, 200, ['message' => $message]);
        } catch (Throwable $e) {
            logError($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param Request $request
     * @param PaymentManager $payment_manager
     * @return JsonResponse
     * @throws InitiateFailedException
     * @throws InvalidPaymentMethod
     * @throws AuthenticationFailedException|FailedToInitiate
     */
    public function recharge(Request $request, PaymentManager $payment_manager): JsonResponse
    {
        $methods = implode(',', $request->user_type == 'affiliate' ? AvailableMethods::getBondhuPointPayments() : AvailableMethods::getWalletRechargePayments());
        $this->validate($request, [
            'payment_method' => 'required|in:' . $methods,
            'amount' => 'required|numeric|min:10|max:100000',
            'user_id' => 'required',
            'user_type' => 'required|in:customer,affiliate,partner',
            'remember_token' => 'required'
        ]);

        $class_name = "App\\Models\\" . ucwords($request->user_type);

        if ($request->user_type === 'partner') {
            $user = (new PartnerRepository($request->user_id))->validatePartner($request->remember_token);
            if($user) (new PartnerStatusAuthentication())->handleInside($user);
        } else {
            $user = $class_name::where([
                ['id', (int)$request->user_id],
                ['remember_token', $request->remember_token]
            ])->first();

            if ($user instanceof Affiliate && $user->isNotVerified()) {
                return api_response($request, null, 403, ['message' => 'অনুগ্রহপূর্বক আপনার বন্ধু প্রোফাইল ভেরিফাই করুন। প্রফাইল ভেরিফিকেশন এর জন্য প্লে স্টোর থেকে বন্ধু মোবাইল অ্যাপ ডাউনলোড করুন এবং এতে দেখানো পদ্ধতি অনুসরণ করুন। কোন সমস্যা সমাধানে কল করুন ১৬৫১৬']);
            }
        }

        if (!$user) return api_response($request, null, 404, ['message' => 'User Not found.']);

        $recharge_adapter = new RechargeAdapter($user, round($request->amount,2));
        $payment = $payment_manager->setMethodName($request->payment_method)->setPayable($recharge_adapter->getPayable())->init();

        return api_response($request, $payment, 200, ['link' => $payment['link'], 'payment' => $payment->getFormattedPayment()]);
    }

    /**
     * @param Request $request
     * @param PaymentStatusChangeLogRepository $paymentRepository
     * @param BonusCredit $bonus_credit
     * @return JsonResponse
     */
    public function purchase(Request $request, PaymentStatusChangeLogRepository $paymentRepository, BonusCredit $bonus_credit): JsonResponse
    {
        $this->validate($request, ['transaction_id' => 'required']);

        /** @var Payment $payment */
        $payment = Payment::where('transaction_id', $request->transaction_id)->valid()->first();
        if (!$payment) return api_response($request, null, 404); elseif ($payment->isFailed()) return api_response($request, null, 500, ['message' => 'Payment failed']);
        elseif ($payment->isPassed()) return api_response($request, null, 200);

        $user = $payment->payable->user;
        $sheba_credit = $user->shebaCredit();
        $paymentRepository->setPayment($payment);
        if ($sheba_credit == 0) {
            $paymentRepository->create(['to' => 'validation_failed', 'from' => $payment->status, 'transaction_details' => $payment->transaction_details, 'log' => "Insufficient balance. Purchase Amount: " . $payment->payable->amount . " & Sheba Credit: $sheba_credit"]);
            $payment->status = 'validation_failed';
            $payment->update();
            return api_response($request, null, 400, ['message' => 'You don\'t have sufficient credit']);
        }

        try {
            $transaction = '';
            DB::transaction(function () use ($payment, $user, $bonus_credit, &$transaction) {
                $spent_model = $payment->payable->getPayableType();
                $is_spend_on_order = $spent_model && ($spent_model instanceof PartnerOrder);
                $category = $is_spend_on_order ? $spent_model->jobs->first()->category : null;
                $category_name = $category ? $category->name : '';
                $bonus_log = $is_spend_on_order ? 'Service Purchased ' . $category_name : 'Purchased ' . class_basename($spent_model);
                $remaining = $bonus_credit->setUser($user)->setPayableType($spent_model)->setLog($bonus_log)->deduct($payment->payable->amount);
                if ($remaining > 0 && $user->wallet > 0) {
                    if ($user->wallet < $remaining) {
                        $remaining = $user->wallet;
                        $payment_detail = $payment->paymentDetails->where('method', 'wallet')->first();
                        $payment_detail->amount = $remaining;
                        $payment_detail->update();
                    }
                    $this->setModifier($user);
                    $transactionHandler = (new WalletTransactionHandler())->setModel($user)->setType(Types::debit())->setAmount($remaining);
                    if (in_array($payment->payable->type, ['movie_ticket_purchase', 'transport_ticket_purchase'])) {
                        $log = sprintf(constants('TICKET_LOG')[$payment->payable->type]['log'], number_format($remaining, 2));
                        $source = ($payment->payable->type == 'movie_ticket_purchase') ? TransactionSources::MOVIE : TransactionSources::TRANSPORT;
                        $transactionHandler->setSource($source);
                    } else {
                        $log = 'Service Purchase';
                        $transactionHandler->setSource(TransactionSources::SERVICE_PURCHASE);
                    }
                    $transactionHandler->setTransactionDetails(['gateway' => TransactionGateways::WALLET]);
                    $transactionHandler->setLog($log);
                    if ($user instanceof Customer) {
                        $transaction = $transactionHandler->store(['event_type' => get_class($spent_model), 'event_id' => $spent_model->id]);
                    } else {
                        $transaction = $transactionHandler->store();
                    }
                }
            });

            $paymentRepository->create([
                'to' => 'validated', 'from' => $payment->status, 'transaction_details' => $payment->transaction_details
            ]);
            $payment->status = 'validated';
            $payment->transaction_details = json_encode(['payment_id' => $payment->id, 'transaction_id' => $transaction ? $transaction->id : null]);
            $payment->update();
        } catch (QueryException $e) {
            $payment->status = 'failed';
            $payment->update();
            logError($e);
            return api_response($request, null, 500);
        }

        return api_response($request, $user, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getFaqs(Request $request): JsonResponse
    {
        $faqs = [
            ['question' => '1. What is Bonus Credit?', 'answer' => 'Bonus credit is a promotional credit which is given by Sheba.xyz to make service purchase at discounted price.'],
            ['question' => '2. How to get bonus credit?', 'answer' => 'You can get bonus credit by purchasing services for which bonus credit offer is running. '],
            ['question' => '3. When does bonus credit expire?', 'answer' => 'From bonus credit list you can check the validity of each bonus credit.'],
            ['question' => '4. Where is bonus credit applicable?', 'answer' => 'Bonus credit can be applied in any sort of service booking. You can pay the full or partial amount of the total bill by bonus credit. '],
            ['question' => '5. What is Voucher?', 'answer' => 'Voucher is a promotional offer to buy bonus credit which can be used in any sort of service purchase. Each voucher has its own validity.'],
            ['question' => '6. How can I purchase Voucher?', 'answer' => 'Sheba voucher can be purchased through any payment method available at payment screen.'],
            ['question' => '7. Is there any hidden charge in purchasing Sheba Voucher?', 'answer' => 'There is no hidden charge applicable.']
        ];

        return api_response($request, $faqs, 200, ['faqs' => $faqs]);
    }
}
