<?php namespace App\Http\Controllers\InfoCall;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Sheba\Dal\InfoCall\Statuses;
use Sheba\ModificationFields;
use Illuminate\Http\Request;

class InfoCallController extends Controller
{
    use ModificationFields;

    public function store($customer, Request $request)
    {
        $customer = Customer::findOrFail($request->customer);
        try {
            $this->setModifier($customer);
            $this->validate($request, [
                'service_name' => 'required|string',
                'mobile' => 'required|string|mobile:bd',
                'location_id' => 'numeric',
            ]);
            $profile = $customer->profile;
            $data = [
                'service_name' => $request->service_name,
                'customer_name' => $profile->name,
                'location_id' => $request->location_id,
                'customer_mobile' => $request->mobile,
                'customer_email' => !empty($profile->email) ? $profile->email : null,
                'customer_address' => !empty($profile->address) ? $profile->address : '',
                'status'=> Statuses::OPEN,
                'follow_up_date' => Carbon::now()->addMinutes(30),
                'intended_closing_date' => Carbon::now()->addMinutes(30)
            ];
            $info_call = $customer->infoCalls()->create($this->withCreateModificationField($data));
            $this->sendNotificationToSD($info_call);
            return api_response($request, 1, 200);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, $e, 500);
        }
    }

    public function sendNotificationToSD($info_call)
    {
        try {
            $sd_but_not_crm = User::where('department_id', 5)->where('is_cm', 0)->pluck('id');
            notify()->users($sd_but_not_crm)->send([
                "title" => 'New Info Call Created by Customer',
                'link' => config('sheba.admin_url') . '/info-call/' . $info_call->id,
                "type" => notificationType('Info')
            ]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
        }
    }


}
