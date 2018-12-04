<?php namespace App\Http\Controllers;

use App\Http\Validators\MobileNumberValidator;
use App\Models\TopUpVendor;
use App\Models\TopUpVendorCommission;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sheba\Helpers\Formatters\BDMobileFormatter;
use Sheba\TopUp\TopUp;
use Sheba\TopUp\TopUpJob;
use Sheba\TopUp\Vendor\Response\Ssl\SslFailResponse;
use Sheba\TopUp\Vendor\VendorFactory;
use Storage;
use Excel;

class TopUpController extends Controller
{
    public function getVendor(Request $request)
    {
        try {
            if ($request->for == 'customer') $agent = "App\\Models\\Customer";
            elseif ($request->for == 'partner') $agent = "App\\Models\\Partner";
            else $agent = "App\\Models\\Affiliate";
            $vendors = TopUpVendor::select('id', 'name', 'is_published')->published()->get();
            $error_message = "Currently, we’re supporting";
            foreach ($vendors as $vendor) {
                $vendor_commission = TopUpVendorCommission::where([['topup_vendor_id', $vendor->id], ['type', $agent]])->first();
                $asset_name = strtolower(trim(preg_replace('/\s+/', '_', $vendor->name)));
                array_add($vendor, 'asset', $asset_name);
                array_add($vendor, 'agent_commission', $vendor_commission->agent_commission);
                array_add($vendor, 'is_prepaid_available', 1);
                array_add($vendor, 'is_postpaid_available', ($vendor->id != 6) ? 1 : 0);
                if ($vendor->is_published) $error_message .= ',' . $vendor->name;
            }
            $regular_expression = array(
                'typing' => "^(013|13|018|18|016|16|017|17|019|19|015|15)",
                'from_contact' => "^(?:\+?88)?01[16|8]\d{8}$",
                'error_message' => $error_message . '.'
            );
            return api_response($request, $vendors, 200, ['vendors' => $vendors, 'regex' => $regular_expression]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function topUp(Request $request, VendorFactory $vendor, TopUp $top_up)
    {
        try {
            $this->validate($request, [
                'mobile' => 'required|string|mobile:bd',
                'connection_type' => 'required|in:prepaid,postpaid',
                'vendor_id' => 'required|exists:topup_vendors,id',
                'amount' => 'required|min:10|max:1000|numeric'
            ]);

            if ($request->affiliate) {
                $agent = $request->affiliate;
            } elseif ($request->customer) {
                $agent = $request->customer;
            } elseif ($request->partner) {
                $agent = $request->partner;
            }

            if ($agent->wallet < (double)$request->amount) return api_response($request, null, 403, ['message' => "You don't have sufficient balance to recharge."]);
            $vendor = $vendor->getById($request->vendor_id);
            if (!$vendor->isPublished()) return api_response($request, null, 403, ['message' => 'Sorry, we don\'t support this operator at this moment']);

            dispatch(new TopUpJob($agent, $request->vendor_id, $request->mobile, $request->amount, $request->connection_type));

            return api_response($request, null, 200, ['message' => "Recharge Request Successful"]);

            // $response = $top_up->setAgent($agent)->setVendor($vendor)->recharge($request->mobile, $request->amount, $request->connection_type);
            // return $response ? api_response($request, null, 200, ['message' => "Recharge Successful"]) : api_response($request, null, 500, ['message' => "Recharge Unsuccessful"]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function topUpTest(Request $request, VendorFactory $vendor, TopUp $top_up)
    {
        try {
            $this->validate($request, [
                'mobile' => 'required|string|mobile:bd',
                'connection_type' => 'required|in:prepaid,postpaid',
                'vendor_id' => 'required|exists:topup_vendors,id',
                'amount' => 'required|min:10|max:1000|numeric'
            ]);

            if ($request->affiliate) {
                $agent = $request->affiliate;
            } elseif ($request->customer) {
                $agent = $request->customer;
            } elseif ($request->partner) {
                $agent = $request->partner;
            }

            if ($agent->wallet < (double)$request->amount) return api_response($request, null, 403, ['message' => "You don't have sufficient balance to recharge."]);
            $vendor = $vendor->getById($request->vendor_id);
            $top_up->setAgent($agent)->setVendor($vendor)->recharge($request->mobile, $request->amount, $request->connection_type);
//            if (!$vendor->isPublished()) return api_response($request, null, 403, ['message' => 'Sorry, we don\'t support this operator at this moment']);
//
//            dispatch(new TopUpJob($agent, $request->vendor_id, $request->mobile, $request->amount, $request->connection_type));

            return api_response($request, null, 200, ['message' => "Recharge Request Successful"]);

            // $response = $top_up->setAgent($agent)->setVendor($vendor)->recharge($request->mobile, $request->amount, $request->connection_type);
            // return $response ? api_response($request, null, 200, ['message' => "Recharge Successful"]) : api_response($request, null, 500, ['message' => "Recharge Unsuccessful"]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function sslFail(Request $request, SslFailResponse $error_response, TopUp $top_up)
    {
        try {
            $data = $request->all();
            $filename = Carbon::now()->timestamp . str_random(6) . '.json';
            Storage::disk('s3')->put("topup/fail/ssl/$filename", json_encode($data));
            $sentry = app('sentry');
            $sentry->user_context(['request' => $data]);
            $sentry->captureException(new \Exception('SSL topup fail'));
            $error_response->setResponse($data);
            $top_up->processFailedTopUp($error_response->getTopUpOrder(), $error_response);
            return api_response($request, 1, 200);
        } catch (QueryException $e) {
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all()]);
            $sentry->captureException($e);
            return api_response($request, null, 500);
        } catch (\Throwable $e) {
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all()]);
            $sentry->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function bulkTopUp(Request $request, VendorFactory $vendor, TopUp $topUp)
    {
        try {
            $this->validate($request, ['file' => 'required|file']);

            $valid_extensions = ["csv", "xls", "xlsx", "xlm", "xla", "xlc", "xlt", "xlw"];
            $extension = $request->file('file')->getClientOriginalExtension();

            if (!in_array($extension, $valid_extensions)) {
                return api_response($request, null, 400, ['message' => 'File type not support']);
            }

            $valid_topups   = collect([]);
            $failed_topups  = collect([]);

            Excel::selectSheets('data')->load($request->file, function ($reader) use ($vendor, $valid_topups, $failed_topups) {
                })->get()->each(function ($value, $key) use ($vendor, $valid_topups, $failed_topups) {
                    $value["mobile"] = BDMobileFormatter::format($value->mobile);
                    $value["vendor_id"] = $vendor->getIdByName($value->operator);

                    $vendor = $vendor->getById($value["vendor_id"]);

                    if (!$vendor->isPublished()) {
                        $value['failed_reason'] = 'Unsupported operator';
                        $failed_topups->push($value);
                        return true;
                    }

                    if (!(new MobileNumberValidator())->validateBangladeshi($value["mobile"])) {
                        $value['failed_reason'] = 'Invalid mobile number';
                        $failed_topups->push($value);
                        return true;
                    }
                    
                    $valid_topups->push($value);
            });

            $agent = null;
            if ($request->affiliate) $agent = $request->affiliate;
            elseif ($request->customer) $agent = $request->customer;
            elseif ($request->partner) $agent = $request->partner;

            foreach ($valid_topups as $topup) {
                dispatch(new TopUpJob($agent, $topup->vendor_id, $topup->mobile, $topup->amount, $topup->connection_type));
            }

            $response_msg = "We have initiated " . $valid_topups->count() . " of your requested top-up and will be transferred shortly. " . $failed_topups->count() . " of your requested top-up is unsuccessful";
            return api_response($request, null, 200, ['message' => $response_msg]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
}