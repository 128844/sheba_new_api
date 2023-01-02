<?php namespace App\Http\Controllers\PaymentLink;

use App\Http\Controllers\Controller;
use App\Sheba\Payment\Adapters\Payable\PaymentLinkOrderAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Sheba\Customer\Creator;
use Illuminate\Http\Request;
use Sheba\Payment\AvailableMethods;
use Sheba\Payment\Exceptions\FailedToInitiate;
use Sheba\Payment\Exceptions\InitiateFailedException;
use Sheba\Payment\Exceptions\InvalidPaymentMethod;
use Sheba\Payment\Exceptions\StoreNotFoundException;
use Sheba\Payment\Factory\PaymentStrategy;
use Sheba\Payment\PaymentManager;
use Sheba\Repositories\Interfaces\PaymentLinkRepositoryInterface;
use Sheba\Dal\ExternalPayment\Model as ExternalPayment;

class PaymentLinkBillController extends Controller
{
    /**
     * @param Request $request
     * @param PaymentManager $payment_manager
     * @param PaymentLinkOrderAdapter $payment_adapter
     * @param Creator $customer_creator
     * @param PaymentLinkRepositoryInterface $repo
     * @return JsonResponse
     * @throws InitiateFailedException
     * @throws InvalidPaymentMethod
     */
    public function clearBill(Request $request, PaymentManager $payment_manager, PaymentLinkOrderAdapter $payment_adapter,
                              Creator $customer_creator, PaymentLinkRepositoryInterface $repo)
    {
        try {
            $availableMethods = AvailableMethods::getPaymentLinkPayments($request->identifier);
            $rules = [
                'amount'     => 'numeric',
                'purpose'    => 'string',
                'identifier' => 'required',
                'name'       => 'required',
                'mobile'     => 'required|string',
            ];

            if ($request->has('emi_month')) {
                $rules['bank_id'] = 'required|integer';
            } else {
                $rules['payment_method'] = 'required|in:' . implode(',', $availableMethods);
            }
            $this->validate($request, $rules);

            $payment_method = $request->payment_method;
            $user           = $customer_creator->setMobile($request->mobile)->setName($request->name)->create();
            $payment_link   = $repo->findByIdentifier($request->identifier);

            if (!empty($payment_link->getEmiMonth()) && (double)$payment_link->getAmount() < config('emi.manager.minimum_emi_amount'))
                return api_response($request, null, 400, ['message' => 'Amount must be greater then or equal BDT ' . config('emi.manager.minimum_emi_amount')]);

            $bank = null;
            if ($payment_link->isEmi()) {
                $bank = $payment_manager->getEmibank($request->bank_id);
            }

            $payable = $payment_adapter->setPayableUser($user)->setPaymentLink($payment_link)
                ->setAmount($request->amount)->setDescription($request->purpose)
                ->setEmiBankId($bank->id)
                ->getPayable();
            if ($payment_method == 'wallet' && $user->shebaCredit() < $payable->amount)
                return api_response($request, null, 403, ['message' => "You don't have sufficient balance"]);
            if ($payment_method === 'online') $payment_method = PaymentStrategy::SSL;
            if ($payment_link->isEmi()) {
                if (!$bank) return response()->json(['code' => 404, 'message' => 'Bank not found']);
                $methodName = $bank->paymentGateway->method_name;
                if (! array_search($methodName, $availableMethods)) $methodName = PaymentStrategy::SSL;
                $payment_method = $methodName ?? PaymentStrategy::SSL;
            }
            try {
                $payment = $payment_manager->setMethodName($payment_method)->setPayable($payable)->init();
            } catch (FailedToInitiate $e) {
                if ($payment_link->isEmi()) {
                    $payment = $payment_manager->setMethodName(PaymentStrategy::SSL)->setPayable($payable)->init(true);
                }else{
                    throw $e;
                }
            }

            $target = $payment_link->getTarget();
            if ($target instanceof ExternalPayment) {
                $target->payment_id = $payment->id;
                $target->update();
            }
            return response()->json([
                'code'    => 200,
                'message' => 'Successful',
                'payment' => $payment->getFormattedPayment()
            ]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (InitiateFailedException $e) {
            logError($e);
            return api_response($request, null, $e->getCode(),['message'=>$e->getMessage()]);
        } catch (StoreNotFoundException $e) {
            logError($e);
            return api_response($request, null, $e->getCode(),['message'=>$e->getMessage()]);
        }
    }
}
