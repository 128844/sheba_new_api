<?php namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PartnerPosCustomer;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sheba\Pos\Customer\Creator;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        try {
            $partner_customers = PartnerPosCustomer::all();
            $customers = collect();
            foreach ($partner_customers as $partner_customer) {
                $customers->push($partner_customer->details());
            }
            return api_response($request, $customers, 200, ['customers' => $customers]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function show($partner,$customer, Request $request)
    {
        try {
            $customer = PartnerPosCustomer::find((int) $customer);
            if($customer)
                return api_response($request, $customer, 200, ['customer' => $customer->details()]);
            else
                return api_response($request, null, 404, ['message' => 'Customer Not Found.']);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function store(Request $request, Creator $creator)
    {
        try {
            $this->validate($request,[
                'mobile' => 'required|mobile:bd',
                'name' => 'required'
            ]);

            $creator = $creator->setData($request->except(['partner_id','remember_token']));
            if ($error = $creator->hasError()) {
                return api_response($request, 1, 400, ['message' => $error['msg']]);
            }
            $customer = $creator->create();
            return api_response($request, $customer, 200, ['customer' => $customer->details()]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            dd($e);
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
}
