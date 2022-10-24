<?php namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\TopUpOrder;
use App\Models\TopUpVendor;
use App\Models\TopUpVendorCommission;
use Sheba\TopUp\Exception\TopUpStillNotResolvedException;
use Sheba\TopUp\Gateway\GatewayFactory;
use Sheba\TopUp\Gateway\Names;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Sheba\Dal\TopUpBulkRequest\TopUpBulkRequest;
use Sheba\Helpers\Formatters\BDMobileFormatter;
use Sheba\TopUp\Creator;
use Sheba\TopUp\Jobs\TopUpExcelJob;
use Sheba\TopUp\Jobs\TopUpJob;
use Sheba\TopUp\TopUpAgent;
use Sheba\TopUp\TopUpDataFormat;
use Sheba\TopUp\TopUpExcel;
use Sheba\TopUp\TopUpLifecycleManager;
use Sheba\TopUp\TopUpRequest;
use Sheba\TopUp\TopUpStatics;
use Sheba\TopUp\Vendor\VendorFactory;
use Sheba\Transactions\Wallet\WalletTransactionHandler;
use Sheba\UserAgentInformation;
use Storage;
use Excel;
use Throwable;
use Hash;
use App\Models\Affiliate;
use Sheba\ModificationFields;
use Sheba\Dal\TopUpOTFSettings\Contract as TopUpOTFSettingsRepo;
use Sheba\Dal\TopUpVendorOTF\Contract as TopUpVendorOTFRepo;

class TopUpController extends Controller
{
    const MINIMUM_TOPUP_INTERVAL_BETWEEN_TWO_TOPUP_IN_SECOND = 10;
    use ModificationFields;

    /** @var TopUpLifecycleManager */
    private $lifecycleManager;

    public function __construct(TopUpLifecycleManager $lifecycle_manager)
    {
        $this->lifecycleManager = $lifecycle_manager;
    }

    public function getVendor(Request $request)
    {
        if ($request->for == 'customer') $agent = Customer::class;
        elseif ($request->for == 'partner') $agent = Partner::class;
        else $agent = Affiliate::class;

        $vendors = TopUpVendor::select('id', 'name', 'waiting_time', 'is_published')->published()->get();
        $error_message = "Currently, we’re supporting";
        foreach ($vendors as $vendor) {
            $vendor_commission = TopUpVendorCommission::where([['topup_vendor_id', $vendor->id], ['type', $agent]])->first();
            $asset_name = strtolower(trim(preg_replace('/\s+/', '_', $vendor->name)));
            array_add($vendor, 'asset', $asset_name);
            array_add($vendor, 'agent_commission', $vendor_commission->agent_commission);
            array_add($vendor, 'is_prepaid_available', 1);
            array_add($vendor, 'is_postpaid_available', ( ! in_array($vendor->id, [6,7]) ) ? 1 : 0);
            if ($vendor->is_published) $error_message .= ',' . $vendor->name;
        }
        $regular_expression = array(
            'typing' => "^(013|13|014|14|018|18|016|16|017|17|019|19|015|15)",
            'from_contact' => "^(?:\+?88)?01[16|8]\d{8}$",
            'error_message' => $error_message . '.'
        );
        return api_response($request, $vendors, 200, ['vendors' => $vendors, 'regex' => $regular_expression]);
    }

    /**
     * @param Request $request
     * @param TopUpRequest $top_up_request
     * @param Creator $creator
     * @param UserAgentInformation $userAgentInformation
     * @return JsonResponse
     * @throws \Exception
     */
    public function topUp(Request $request, TopUpRequest $top_up_request, Creator $creator, UserAgentInformation $userAgentInformation)
    {
        $this->validate($request, [
            'mobile' => 'required|string|mobile:bd',
            'connection_type' => 'required|in:prepaid,postpaid',
            'vendor_id' => 'required|exists:topup_vendors,id',
            'amount' => 'required|min:10|max:1000|numeric',
            'is_robi_topup' => 'sometimes|in:0,1'
        ]);

        $agent = $this->getAgent($request);
        $userAgentInformation->setRequest($request);
        if ($this->hasLastTopupWithinIntervalTime($agent))
            return api_response($request, null, 400, ['message' => 'Wait another minute to topup']);
        if ($agent instanceof Partner) {
            //freeze money amount check
            WalletTransactionHandler::isDebitTransactionAllowed($agent, $request->amount, 'টপ আপ করার');
        }
        $top_up_request->setAmount($request->amount)
            ->setMobile($request->mobile)->setType($request->connection_type)
            ->setAgent($agent)->setVendorId($request->vendor_id)->setRobiTopupWallet($request->is_robi_topup)
            ->setUserAgent($userAgentInformation->getUserAgent());

        if ($top_up_request->hasError())
            return api_response($request, null, 403, ['message' => $top_up_request->getErrorMessage()]);

        $topup_order = $creator->setTopUpRequest($top_up_request)->create();

        if (!$topup_order) return api_response($request, null, 500);

        dispatch((new TopUpJob($topup_order)));
        return api_response($request, null, 200, ['message' => "Recharge Request Successful", 'id' => $topup_order->id]);
    }

    /**
     * @param Request $request
     * @param VendorFactory $vendor
     * @param TopUpRequest $top_up_request
     * @param Creator $creator
     * @return JsonResponse
     * @throws \Exception
     */
    public function bulkTopUp(Request $request, VendorFactory $vendor, TopUpRequest $top_up_request, Creator $creator)
    {
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
        $bulk_request = $this->storeBulkRequest($agent);

        $data->each(function ($value, $key) use ($creator, $vendor, $agent, $file_path, $top_up_request, $total, $bulk_request) {
            $operator_field = TopUpExcel::VENDOR_COLUMN_TITLE;
            $type_field = TopUpExcel::TYPE_COLUMN_TITLE;
            $mobile_field = TopUpExcel::MOBILE_COLUMN_TITLE;
            $amount_field = TopUpExcel::AMOUNT_COLUMN_TITLE;

            if (!$value->$operator_field) return;

            $vendor_id = $vendor->getIdByName($value->$operator_field);
            $request = $top_up_request->setType($value->$type_field)
                ->setMobile(BDMobileFormatter::format($value->$mobile_field))->setAmount($value->$amount_field)->setAgent($agent)->setVendorId($vendor_id);
            $topup_order = $creator->setTopUpRequest($request)->create();
            if (!$top_up_request->hasError()) dispatch(new TopUpExcelJob($topup_order, $file_path, $key + 2, $total, $bulk_request));
        });

        $response_msg = "Your top-up request has been received and will be transferred and notified shortly.";
        return api_response($request, null, 200, ['message' => $response_msg]);
    }

    public function storeBulkRequest($agent)
    {
        $topup_bulk_request = new TopUpBulkRequest();
        $topup_bulk_request->agent_id = $agent->id;
        $topup_bulk_request->agent_type = $this->getFullAgentType($agent->type);
        $topup_bulk_request->status = constants('TOPUP_BULK_REQUEST_STATUS')['pending'];
        $topup_bulk_request->save();
        return $topup_bulk_request;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @throws Throwable
     */
    public function sslFail(Request $request)
    {
        $request->merge(['is_from_success_url' => false]);
        $this->ipnHandle($request, Names::SSL);
        return api_response($request, 1, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     * @throws Throwable
     */
    public function sslSuccess(Request $request)
    {
        $request->merge(['is_from_success_url' => true]);
        $this->ipnHandle($request, Names::SSL);
        return api_response($request, 1, 200);
    }

    private function getAgent(Request $request)
    {
        if ($request->affiliate) return $request->affiliate;
        elseif ($request->customer) return $request->customer;
        elseif ($request->partner) return $request->partner;
        elseif ($request->vendor) return $request->vendor;
        elseif ($request->business) return $request->business;
    }

    private function getFullAgentType($type)
    {
        switch ($type) {
            case 'customer': return Customer::class;
            case 'partner': return Partner::class;
            case 'affiliate': return Affiliate::class;
            case 'business': case 'Company': return Business::class;
            default: return '';
        }
    }

    private function hasLastTopupWithinIntervalTime(TopUpAgent $agent)
    {
        $last_topup = $agent->topups()->select('id', 'created_at')->orderBy('id', 'desc')->first();
        return $last_topup && $last_topup->created_at->diffInSeconds(Carbon::now()) < self::MINIMUM_TOPUP_INTERVAL_BETWEEN_TWO_TOPUP_IN_SECOND;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function topUpOTF(Request $request): JsonResponse
    {
        $this->validate($request, TopUpStatics::topUpOTFRequestValidate());

        $vendor = TopUpVendor::select('id', 'name', 'gateway','is_published')->where('id', $request->vendor_id)->published()->first();

        if (!$vendor) {
            $message = "Vendor not found";
            return api_response($request, $message, 404, ['message' => $message]);
        }

        $topup_otf_settings = app(TopUpOTFSettingsRepo::class);
        $topup_vendor_otf = app(TopUpVendorOTFRepo::class);

        $agent = $this->getFullAgentType($request->for);

        $otf_settings = $topup_otf_settings->builder()->where([
            ['topup_vendor_id', $request->vendor_id], ['type', $agent]
        ])->first();
        if ($otf_settings && $otf_settings->applicable_gateways != 'null' && in_array($vendor->gateway, json_decode($otf_settings->applicable_gateways)) == true) {
            $otf_list = $topup_vendor_otf->builder()->where('topup_vendor_id', $request->vendor_id)->where('sim_type', 'like', '%' . $request->sim_type . '%')->where('status', 'Active')->orderBy('cashback_amount', 'DESC')->get();

            (new TopUpDataFormat())->makeTopUpOTFData($request->vendor_id, $agent, $request->partner, $vendor->name, $otf_settings, $otf_list);

            return api_response($request, $otf_list, 200, ['data' => $otf_list]);
        } else {
            $otf_list = [];
            return api_response($request, $otf_list, 200, ['message' => $otf_list]);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function topUpOTFDetails(Request $request): JsonResponse
    {
        $this->validate($request, TopUpStatics::topUpOTFDetailsValidate());

        $vendor = TopUpVendor::select('id', 'name', 'gateway', 'is_published')
            ->where('id', $request->vendor_id)
            ->published()->first();

        if (!$vendor) {
            $message = "Vendor not found";
            return api_response($request, $message, 404, ['message' => $message]);
        }

        $topup_otf_settings = app(TopUpOTFSettingsRepo::class);
        $topup_vendor_otf = app(TopUpVendorOTFRepo::class);

        $agent = $this->getFullAgentType($request->for);
        $otf_settings = $topup_otf_settings->builder()->where([
            ['topup_vendor_id', $request->vendor_id], ['type', $agent]
        ])->first();

        if ($otf_settings->applicable_gateways != 'null' && in_array($vendor->gateway, json_decode($otf_settings->applicable_gateways)) == true) {
            $otf_list = $topup_vendor_otf->builder()->where('id', $request->otf_id)->where('status', 'Active')->get();

            (new TopUpDataFormat())->makeTopUpOTFData($request->vendor_id, $agent, $request->partner, $vendor->name, $otf_settings, $otf_list);

            return api_response($request, $otf_list, 200, ['data' => $otf_list]);
        } else {
            $otf_list = [];
            return api_response($request, $otf_list, 200, ['message' => $otf_list]);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws Throwable
     */
    public function statusUpdate(Request $request)
    {
        /** @var TopUpOrder $top_up_order */
        $top_up_order = TopUpOrder::find($request->topup_order_id);
        if (!$top_up_order->canRefresh()) {
            $message = "Top up is already " . $top_up_order->status;
            return api_response($request, $message, 404, [
                'code' => 400,
                'message' => $message
            ]);
        }

        try {
            $actual_response = $this->lifecycleManager->setTopUpOrder($top_up_order)->reload()->getResponse();
        } catch (TopUpStillNotResolvedException $e) {
            $actual_response = $e->getResponse();
        }

        return api_response($request, 1, 200, [
            'actual_response' => $actual_response
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws Exception|Throwable
     */
    public function bdRechargeStatusUpdate(Request $request)
    {
        $this->ipnHandle($request, Names::BD_RECHARGE);
        return api_response($request, 1, 200);
    }

    /**
     * @throws Throwable
     */
    public function payStationStatusUpdate(Request $request): JsonResponse
    {
        $this->ipnHandle($request, Names::PAY_STATION);
        return api_response($request, 1, 200);
    }

    /**
     * @throws Throwable
     */
    private function ipnHandle(Request $request, $gateway_name)
    {
        $gateway = GatewayFactory::getIpnGatewayByName($gateway_name);
        $data = $request->all();
        $this->lifecycleManager->handleIpn($gateway, $data);
    }
}
