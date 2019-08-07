<?php namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PartnerPosSetting;
use App\Models\PosCustomer;
use App\Repositories\SmsHandler as SmsHandlerRepo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sheba\ModificationFields;
use Sheba\Pos\Setting\Creator;
use Throwable;

class SettingController extends Controller
{
    use ModificationFields;

    /**
     * @param Request $request
     * @param Creator $creator
     * @return JsonResponse
     */
    public function getSettings(Request $request, Creator $creator)
    {
        try {
            $partner = $request->partner;
            $settings = PartnerPosSetting::byPartner($partner->id)->first();
            if (!$settings) {
                $data = ['partner_id' => $partner->id,];
                $creator->setData($data)->create();
                $settings = PartnerPosSetting::byPartner($partner->id)->first();
            }
            removeRelationsAndFields($settings);
            return api_response($request, $settings, 200, ['settings' => $settings]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function duePaymentRequestSms(Request $request)
    {
        try {
            $this->validate($request, ['customer_id' => 'required|numeric', 'due_amount' => 'required']);
            $partner = $request->partner;
            $this->setModifier($request->manager_resource);

            $customer = PosCustomer::find($request->customer_id);
            (new SmsHandlerRepo('due-payment-collect-request'))->setVendor('infobip')
                ->send($customer->profile->mobile, [
                    'partner_name' => $partner->name,
                    'due_amount' => $request->due_amount
                ]);

            return api_response($request, null, 200, ['msg' => 'SMS Send Successfully']);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
}