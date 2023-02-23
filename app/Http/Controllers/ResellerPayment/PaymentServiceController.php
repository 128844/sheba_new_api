<?php

namespace App\Http\Controllers\ResellerPayment;

use App\Exceptions\NotFoundAndDoNotReportException;
use App\Exceptions\NotFoundException;
use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Sheba\ResellerPayment\Exceptions\MORServiceServerError;
use App\Sheba\ResellerPayment\Exceptions\UnauthorizedRequestFromMORException;
use App\Sheba\ResellerPayment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sheba\ResellerPayment\Exceptions\InvalidKeyException;
use Sheba\ResellerPayment\Exceptions\ResellerPaymentException;
use Sheba\ResellerPayment\Statics\ResellerPaymentGeneralStatic;
use Throwable;

class PaymentServiceController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws NotFoundAndDoNotReportException
     * @throws MORServiceServerError
     * @throws InvalidKeyException
     */
    public function getPaymentGateway(Request $request): JsonResponse
    {
        $completion = $request->query('completion');
        $banner = $request->query('banner');
        $header_message = 'সর্বাধিক ব্যবহৃত';
        $partnerId = $request->partner->id;
        $version_code = $request->header('version-code');
        $pgwData = $this->paymentService->setPartner($request->partner)->getPaymentGateways($completion, $header_message, $partnerId, $banner,$version_code);

        return api_response($request, null, 200, ['data' => $pgwData]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getPaymentServiceCharge(Request $request): JsonResponse
    {
        $partnerId = $request->partner->id;
        $data = $this->paymentService->getServiceCharge($partnerId);
        return api_response($request, null, 200, ['data' => $data]);
    }


    /**
     * @param Request $request
     * @return JsonResponse
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
     * @return JsonResponse
     */
    public function emiInformationForManager(Request $request)
    {
        try {
            $partner = $request->partner;

            $this->validate($request, ['amount' => 'required|numeric|min:' . config('emi.manager.minimum_emi_amount')]);
            $amount = $request->amount;

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

    /**
     * @param Request $request
     * @param PaymentService $paymentService
     * @return JsonResponse
     * @throws MORServiceServerError
     * @throws NotFoundAndDoNotReportException
     */
    public function bannerAndStatus(Request $request, PaymentService $paymentService): JsonResponse
    {
        $partner = $request->partner;
        $data = $paymentService->setPartner($partner)->getStatusAndBanner();
        return api_response($request, null, 200, ['data' => $data]);
    }

    /**
     * @param Request $request
     * @param PaymentService $paymentService
     * @return JsonResponse
     * @throws MORServiceServerError
     * @throws NotFoundAndDoNotReportException
     * @throws ResellerPaymentException
     */
    public function getPaymentGatewayDetails(Request $request, PaymentService $paymentService): JsonResponse
    {
        $this->validate($request, [
            'key' => 'required',
            'payment_type' => 'sometimes|in:pgw,qr'
        ]);
        $partner = $request->partner;
        $detail = $paymentService->setPartner($partner)->setKey($request->key)->setType($request->payment_type)->getDetails();
        return api_response($request, null, 200, ['data' => $detail]);
    }

    /**
     * @throws UnauthorizedRequestFromMORException
     * @throws NotFoundException
     */
    public function sendNotificationOnStatusChange(Request $request, PaymentService $paymentService): JsonResponse
    {
        $this->validate($request, ResellerPaymentGeneralStatic::notificationSubmitValidation());
        $paymentService->authenticateMorRequest($request->header('access-key'));

        $partner = Partner::find($request->partner_id);
        if (!$partner)
            throw new NotFoundException("Invalid Partner Id");


        $paymentService->setKey($request->key)->setPartner($partner)->setNewStatus($request->new_status)->sendNotificationOnStatusChange();
        return api_response($request, null, 200, ["message" => 'Notification sent successfully']);
    }

    /**
     * @param Request $request
     * @param PaymentService $paymentService
     * @return JsonResponse
     * @throws NotFoundException
     * @throws UnauthorizedRequestFromMORException
     */
    public function sendCustomSMS(Request $request, PaymentService $paymentService): JsonResponse
    {
        $this->validate($request, ResellerPaymentGeneralStatic::smsSendValidation());
        $paymentService->authenticateMorRequest($request->header('access-key'));
        $partner = Partner::find($request->partner_id);
        if (!$partner)
            throw new NotFoundException("Invalid Partner Id");

        $paymentService->setKey($request->key)->setPartner($partner)->setNewStatus($request->new_status)->sendSMS($request->sms_body);
        return api_response($request, null, 200, ["message" => 'SMS sent successfully']);

    }
}
