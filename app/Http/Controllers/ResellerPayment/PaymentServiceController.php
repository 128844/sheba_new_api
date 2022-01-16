<?php

namespace App\Http\Controllers\ResellerPayment;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Sheba\ResellerPayment\PaymentGateway\PaymentGateway;
use App\Sheba\ResellerPayment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentServiceController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function getPaymentGateway(Request $request)
    {
        try {
            $completion = $request->query('completion');
            $header_message = 'সর্বাধিক ব্যবহৃত';
            $partnerId = $request->partner->id;

            $pgwData = $this->paymentService->getPaymentGateways($completion, $header_message, $partnerId);
            return api_response($request, null, 200, ['data' => $pgwData]);
        } catch (\Throwable $e) {
            logError($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentServiceCharge(Request $request)
    {
        try {
            $partnerId = $request->partner->id;

            $data = $this->paymentService->getServiceCharge($partnerId);
            return api_response($request, null, 200, ['data' => $data]);
        } catch (\Throwable $e) {
            logError($e);
            return api_response($request, null, 500);
        }
    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storePaymentServiceCharge(Request $request)
    {
        try {
            $this->validate($request, [
                "current_percentage" => "required | numeric"
            ]);

            $partnerId = $request->partner->id;
            $currentPercentage = $request->current_percentage;

            $this->paymentService->storeServiceCharge($partnerId, $currentPercentage);

            return api_response($request, null, 200);
        } catch (ValidationException $e) {
            $msg = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, null, 400, ['message' => $msg]);
        } catch (\Throwable $e) {
            logError($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function emiInformationForManager(Request $request)
    {
        try {
            $partner = $request->partner;

            $this->validate($request, ['amount' => 'required|numeric|min:' . config('emi.manager.minimum_emi_amount')]);
            $amount       = $request->amount;

            $emi_data = $this->paymentService->getEmiInfoForManager($partner, $amount);

            return api_response($request, null, 200, ['price' => $amount, 'info' => $emi_data]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function bannerAndStatus(Request $request, PaymentService $paymentService)
    {
        $partner = $request->partner;

        //$data = $paymentService->setPartner($partner)->getStatusAndBanner();

        $data = [
            'banner' => 'https://cdn-shebadev.s3.ap-south-1.amazonaws.com/reseller_payment/not_started_journey.png',
            'status' => null,
            'pgw_status' => 0,
        ];

        return api_response($request, null, 200, ['data' => $data]);
    }

    public function getPaymentGatewayDetails(Request $request, PaymentService $paymentService)
    {
        $this->validate($request, [
            'key' => 'required|in:'.implode(',', config('reseller_payment.available_payment_gateway_keys'))
        ]);
        $partner = $request->partner;
        $detail = $paymentService->setPartner($partner)->setKey($request->key)->getPGWDetails();
        return api_response($request, null, 200, ['data' => $detail]);
    }
}