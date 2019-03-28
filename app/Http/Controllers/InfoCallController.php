<?php namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Sheba\ModificationFields;
use Illuminate\Http\Request;
use App\Models\InfoCall;

class InfoCallController extends Controller
{
    use ModificationFields;

    public function index($customer, Request $request)
    {
        try {
            $customer = $request->customer;
            #InfoCall::where('customer_id', (int)$customer->id)->orderBy('created_at', 'desc')->get();
            $info_calls = $customer->infoCalls()->orderBy('created_at', 'DESC')->get();
            $info_call_lists = collect([]);
            foreach ($info_calls as $info_call) {
                $info = [
                    'id' => $info_call->id,
                    'code' => $info_call->code(),
                    'service_name' => $info_call->service_name,
                    'status' => $info_call->status,
                    'created_at' => $info_call->created_at->format('F j, Y'),
                ];
                $info_call_lists->push($info);
            }
            return api_response($request, $info_call_lists, 200, ['info_call_lists' => $info_call_lists]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getDetails($customer, $info_call, Request $request)
    {
        try {
            $customer = $request->customer;
            $info_call = InfoCall::find($info_call);
            $details = [
                'id' => $info_call->id,
                'code' => $info_call->code(),
                'service_name' => $info_call->service_name,
                'status' => $info_call->status,
                'created_at' => $info_call->created_at->format('F j, h:ia'),
                'estimated_budget' => $info_call->estimated_budget,
            ];

            return api_response($request, $details, 200, ['details' => $details]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function store($customer, Request $request)
    {
        try {
            $this->setModifier($request->customer);
            $this->validate($request, [
                'service_name' => 'required|string',
                'estimated_budget' => 'required|numeric'
            ]);
            $customer = $request->customer;
            $profile = $customer->profile;

            $data = [
                'service_name' => $request->service_name,
                'estimated_budget' => $request->estimated_budget,
                'customer_name' => $profile->name,
                'customer_mobile' => $profile->mobile,
                'customer_email' => !empty($profile->email) ? $profile->email : null,
                'customer_address' => !empty($profile->address) ? $profile->address : null,
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
            return api_response($request, null, 500);
        }
    }

    public function sendNotificationToSD($info_call)
    {
        try {
            $sd_but_not_crm = User::where('department_id', 5)->where('is_cm', 0)->pluck('id');
            notify()->users($sd_but_not_crm)->send([
                "title" => 'New Info Call Created by Customer',
                'link' => env('SHEBA_BACKEND_URL') . '/info-call/' . $info_call->id,
                "type" => notificationType('Info')
            ]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
        }
    }

}
