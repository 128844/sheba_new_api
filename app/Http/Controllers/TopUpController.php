<?php namespace App\Http\Controllers;

use App\Models\TopUpOrder;
use App\Models\TopUpVendor;
use App\Models\TopUpVendorCommission;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;
use Sheba\Helpers\Formatters\BDMobileFormatter;
use Sheba\TopUp\Creator;
use Sheba\TopUp\TopUp;
use Sheba\TopUp\Jobs\TopUpExcelJob;
use Sheba\TopUp\Jobs\TopUpJob;
use Sheba\TopUp\TopUpExcel;
use Sheba\TopUp\TopUpRequest;
use Sheba\TopUp\Vendor\Response\Ipn\Ssl\SslSuccessResponse;
use Sheba\TopUp\Vendor\Response\Ssl\SslFailResponse;
use Sheba\TopUp\Vendor\VendorFactory;
use Storage;
use Excel;
use Throwable;

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
                'typing' => "^(013|13|014|14|018|18|016|16|017|17|019|19|015|15)",
                'from_contact' => "^(?:\+?88)?01[16|8]\d{8}$",
                'error_message' => $error_message . '.'
            );
            return api_response($request, $vendors, 200, ['vendors' => $vendors, 'regex' => $regular_expression]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function topUp(Request $request, TopUpRequest $top_up_request, Creator $creator)
    {
        try {
            $this->validate($request, [
                'mobile' => 'required|string|mobile:bd',
                'connection_type' => 'required|in:prepaid,postpaid',
                'vendor_id' => 'required|exists:topup_vendors,id',
                'amount' => 'required|min:10|max:1000|numeric'
            ]);
            $agent = $this->getAgent($request);
            $top_up_request->setAmount($request->amount)->setMobile($request->mobile)->setType($request->connection_type)->setAgent($agent)->setVendorId($request->vendor_id);
            if ($top_up_request->hasError()) return api_response($request, null, 403, ['message' => $top_up_request->getErrorMessage()]);
            $topup_order = $creator->setTopUpRequest($top_up_request)->create();
            if ($topup_order) {
                dispatch((new TopUpJob($agent, $request->vendor_id, $topup_order)));
                return api_response($request, null, 200, ['message' => "Recharge Request Successful", 'id' => $topup_order->id]);
            } else {
                return api_response($request, null, 500);
            }
        } catch (ValidationException $e) {
            app('sentry')->captureException($e);
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function bulkTopUp(Request $request, VendorFactory $vendor, TopUpRequest $top_up_request, Creator $creator)
    {
        try {
            $this->validate($request, ['file' => 'required|file']);

            $valid_extensions = ["xls", "xlsx", "xlm", "xla", "xlc", "xlt", "xlw"];
            $extension = $request->file('file')->getClientOriginalExtension();

            if (!in_array($extension, $valid_extensions)) {
                return api_response($request, null, 400, ['message' => 'File type not support']);
            }

            $agent = $this->getAgent($request);

            $file = Excel::selectSheets(TopUpExcel::SHEET)->load($request->file)->save();
            $file_path = $file->storagePath . DIRECTORY_SEPARATOR . $file->getFileName() . '.' . $file->ext;

            $data = Excel::selectSheets(TopUpExcel::SHEET)->load($file_path)->get();
            $total = $data->count();
            $data->each(function ($value, $key) use ($creator, $vendor, $agent, $file_path, $top_up_request, $total) {
                $operator_field = TopUpExcel::VENDOR_COLUMN_TITLE;
                $type_field = TopUpExcel::TYPE_COLUMN_TITLE;
                $mobile_field = TopUpExcel::MOBILE_COLUMN_TITLE;
                $amount_field = TopUpExcel::AMOUNT_COLUMN_TITLE;

                if (!$value->$operator_field) return;

                $vendor_id = $vendor->getIdByName($value->$operator_field);
                $request = $top_up_request->setType($value->$type_field)
                    ->setMobile(BDMobileFormatter::format($value->$mobile_field))->setAmount($value->$amount_field)->setAgent($agent)->setVendorId($vendor_id);
                $topup_order = $creator->setTopUpRequest($request)->create();
                if (!$top_up_request->hasError()) dispatch(new TopUpExcelJob($agent, $vendor_id, $topup_order, $file_path, $key + 2, $total));
            });

            $response_msg = "Your top-up request has been received and will be transferred and notified shortly.";
            return api_response($request, null, 200, ['message' => $response_msg]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (Throwable $e) {
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
            $sentry->captureException(new Exception('SSL topup fail'));
            $error_response->setResponse($data);
            $top_up->processFailedTopUp($error_response->getTopUpOrder(), $error_response);

            $topup_fail_namespace = 'Topup:Fail_' . Carbon::now()->timestamp . str_random(6);
            Redis::set($topup_fail_namespace, json_encode($data));

            return api_response($request, 1, 200);
        } catch (QueryException $e) {
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all()]);
            $sentry->captureException($e);
            return api_response($request, null, 500);
        } catch (Throwable $e) {
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all()]);
            $sentry->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function sslSuccess(Request $request, SslSuccessResponse $success_response, TopUp $top_up)
    {
        try {
            $data = $request->all();
            $filename = Carbon::now()->timestamp . str_random(6) . '.json';
            Storage::disk('s3')->put("topup/success/ssl/$filename", json_encode($data));
            $success_response->setResponse($data);
            $top_up->processSuccessfulTopUp($success_response->getTopUpOrder(), $success_response);

            /**
             * USE ONLY FOR TEMPORARY CHECK
             *
             * $topup_success_namespace = 'Topup:Success_'. Carbon::now()->timestamp . str_random(6);
             * Redis::set($topup_success_namespace, json_encode($data));
             */

            return api_response($request, 1, 200);
        } catch (QueryException $e) {
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all()]);
            $sentry->captureException($e);
            return api_response($request, null, 500);
        } catch (Throwable $e) {
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all()]);
            $sentry->captureException($e);
            return api_response($request, null, 500);
        }
    }

    private function getAgent(Request $request)
    {
        if ($request->affiliate) return $request->affiliate;
        elseif ($request->customer) return $request->customer;
        elseif ($request->partner) return $request->partner;
        elseif ($request->vendor) return $request->vendor;
    }

    /**
     * TEST CONTROLLER FOR TOPUP TEST
     * @param Request $request
     * @param VendorFactory $vendor
     * @param TopUp $top_up
     * @param TopUpRequest $top_up_request
     * @return JsonResponse
     */
    public function topUpTest(Request $request, VendorFactory $vendor, TopUp $top_up, TopUpRequest $top_up_request)
    {
        try {
            $this->validate($request, [
                'mobile' => 'required|string|mobile:bd',
                'connection_type' => 'required|in:prepaid,postpaid',
                'vendor_id' => 'required|exists:topup_vendors,id',
                'amount' => 'required|min:10|max:1000|numeric'
            ]);

            $agent = $this->getAgent($request);
            if ($agent->wallet < (double)$request->amount) return api_response($request, null, 403, ['message' => "You don't have sufficient balance to recharge."]);
            $vendor = $vendor->getById($request->vendor_id);
            $topUprequest = $top_up_request->setAmount($request->amount)->setMobile($request->mobile)->setType($request->connection_type);
            $top_up->setAgent($agent)->setVendor($vendor)->recharge($topUprequest);

            if (!$vendor->isPublished()) return api_response($request, null, 403, ['message' => 'Sorry, we don\'t support this operator at this moment']);

            return api_response($request, null, 200, ['message' => "Recharge Request Successful"]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function restartQueue()
    {
        $queue_name = isInProduction() ? "sheba_queues:topup_00" : "sheba_queues:topup";
        $folder = isInProduction() ? "/var/www/api" : "/var/www/sheba_new_api";
        exec("sudo supervisorctl restart $queue_name");
        exec("cd $folder && php artisan queue:restart");
        return ['code' => 200, 'message' => "Done."];
    }
}
