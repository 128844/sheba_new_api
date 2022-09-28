<?php namespace App\Http\Controllers\TopUp;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\TopUpVendor;
use App\Sheba\TopUp\TopUpBulkRequest\Formatter as TopUpBulkRequestFormatter;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Sheba\Dal\AuthenticationRequest\Purpose;
use Sheba\Dal\TopUpBulkRequest\TopUpBulkRequest;
use Sheba\Dal\TopUpBulkRequestNumber\TopUpBulkRequestNumber;

use Sheba\Dal\TopupOrder\TopUpOrderRepository;
use Sheba\ModificationFields;
use Sheba\TopUp\Bulk\RequestStatus;
use Sheba\TopUp\Bulk\Validator\DataFormatValidator;
use Sheba\TopUp\Bulk\Validator\ExtensionValidator;
use Sheba\TopUp\Bulk\Validator\SheetNameValidator;

use Sheba\TopUp\ConnectionType;
use Sheba\OAuth2\AuthUser;
use Sheba\TopUp\Exception\InvalidTopUpTokenException;
use Sheba\TopUp\History\RequestBuilder;
use Sheba\TopUp\OTF\OtfAmount;
use Sheba\TopUp\TopUpAgent;
use Sheba\TopUp\TopUpAgentBlocker;
use Sheba\TopUp\TopUpChargesSubscriptionWise;
use Sheba\TopUp\TopUpDataFormat;
use Sheba\TopUp\TopUpHistoryExcel;
use Sheba\TopUp\TopUpSpecialAmount;
use Sheba\OAuth2\VerifyPin;
use Sheba\TopUp\TopUpTokenManager;
use Sheba\UserAgentInformation;
use DB;
use Excel;
use Illuminate\Http\Request;
use Sheba\Helpers\Formatters\BDMobileFormatter;
use Sheba\TopUp\Creator;
use Sheba\TopUp\Jobs\TopUpExcelJob;
use Sheba\TopUp\Jobs\TopUpJob;
use Sheba\TopUp\TopUpRechargeManager;
use Sheba\TopUp\TopUpExcel;
use Sheba\TopUp\TopUpRequest;
use Sheba\TopUp\Vendor\VendorFactory;
use Storage;
use Validator;

class TopUpController extends Controller
{
    use ModificationFields;

    /** @var TopUpTokenManager */
    private $tokenManager;

    public function __construct(TopUpTokenManager $token_manager)
    {
        $this->tokenManager = $token_manager;
    }

    /**
     * @param Request $request
     * @param TopUpDataFormat $formatter
     * @param string $user
     * @return JsonResponse
     */
    public function getVendor(Request $request, TopUpDataFormat $formatter, string $user = ''): JsonResponse
    {
        $topup_charges = [];
        /** @var TopUpAgent $agent */
        $agent = $this->getAgent($request, $user);
        $agent_class = get_class($agent);

        if ($agent_class === "App\Models\Partner")
            $topup_charges = (new TopUpChargesSubscriptionWise())->getCharges($agent);

        $vendors = TopUpVendor::select('id', 'name', 'is_published')->published()->get();

        foreach ($vendors as $vendor)
            $formatter->makeVendorWiseCommissionData($vendor, $agent_class, $topup_charges);

        $regular_expression = $formatter->getAdditionalData();
        return api_response($request, $vendors, 200, ['vendors' => $vendors, 'regex' => $regular_expression]);
    }

    /**
     * @param Request $request
     * @param $user
     * @param TopUpRequest $top_up_request
     * @param Creator $creator
     * @param UserAgentInformation $userAgentInformation
     * @param VerifyPin $verifyPin
     * @param TopUpAgentBlocker $agent_blocker
     * @return JsonResponse
     * @throws \App\Exceptions\DoNotReportException
     * @throws \Sheba\OAuth2\AccountServerAuthenticationError
     * @throws \Sheba\OAuth2\AccountServerNotWorking
     * @throws \Sheba\OAuth2\WrongPinError
     * @throws \Sheba\TopUp\Exception\PinMismatchException
     */
    public function topUp(Request $request, $user, TopUpRequest $top_up_request, Creator $creator, UserAgentInformation $userAgentInformation, VerifyPin $verifyPin, TopUpAgentBlocker $agent_blocker)
    {
        $agent = $request->user;
        $validation_data = [
            'mobile' => 'required|string|mobile:bd',
            'connection_type' => 'required|in:prepaid,postpaid',
            'vendor_id' => 'required|exists:topup_vendors,id',
            'password' => 'required'
        ];

        if ($this->isBusiness($agent) && $this->isPrepaid($request->connection_type)) {
            $validation_data['amount'] = 'required|numeric|min:10|max:' . $agent->topup_prepaid_max_limit;
        } elseif ($this->isBusiness($agent) && $this->isPostpaid($request->connection_type)) {
            $validation_data['amount'] = 'required|numeric|min:10';
        } else {
            $validation_data['amount'] = 'required|min:10|max:1000|numeric';
        }

        $this->validate($request, $validation_data);

        /** @var AuthUser $auth_user */
        $auth_user = $request->auth_user;
        if ($user == 'business') $agent = $auth_user->getBusiness();
        elseif ($user == 'affiliate') $agent = $auth_user->getAffiliate();
        elseif ($user == 'partner') {
            $agent = $auth_user->getPartner();
            try {
                $this->tokenManager->setPartner($agent)->validate($request->topup_token);
            } catch (InvalidTopUpTokenException $e) {
                // logError($e);
            }
        } else return api_response($request, null, 400);

        $verifyPin->setAgent($agent)->setProfile($request->access_token->authorizationRequest->profile)->setPurpose(Purpose::TOPUP)->setRequest($request)->verify();

        $userAgentInformation->setRequest($request);
        $top_up_request->setAmount($request->amount)
            ->setMobile($request->mobile)
            ->setType($request->connection_type)
            ->setAgent($agent)
            ->setVendorId($request->vendor_id)
            ->setLat($request->lat ?: null)
            ->setLong($request->long ?: null)
            ->setUserAgent($userAgentInformation->getUserAgent())
            ->setIsOtfAllow(!$request->is_otf_allow);

        if ($top_up_request->hasError()) {
            return api_response($request, null, $top_up_request->getErrorCode(), ['message' => $top_up_request->getErrorMessage()]);
        }

        $topup_order = $creator->setTopUpRequest($top_up_request)->create();

        $agent_blocker->setAgent($agent)->checkAndBlock();

        if (!$topup_order) return api_response($request, null, 500);

        dispatch((new TopUpJob($topup_order)));

        return api_response($request, null, 200, ['message' => "Recharge Request Successful", 'id' => $topup_order->id]);
    }

    public function isBusiness($agent)
    {
        return $agent instanceof Business;
    }

    public function isPrepaid($connection_type)
    {
        return $connection_type == ConnectionType::PREPAID;
    }

    public function isPostpaid($connection_type)
    {
        if ($connection_type == ConnectionType::POSTPAID) return true;
        return false;
    }

    /**
     * @param Request $request
     * @param VerifyPin $verifyPin
     * @param VendorFactory $vendor
     * @param TopUpRequest $top_up_request
     * @param Creator $creator
     * @param TopUpSpecialAmount $special_amount
     * @return JsonResponse
     * @throws Exception
     */
    public function bulkTopUp(Request $request, VerifyPin $verifyPin, VendorFactory $vendor, TopUpRequest $top_up_request,
                              Creator $creator, TopUpSpecialAmount $special_amount): JsonResponse
    {
        $this->validate($request, ['file' => 'required|file', 'password' => 'required']);

        $agent = $request->user;
        $this->setModifier($agent);

        $verifyPin->setAgent($agent)
            ->setProfile($request->access_token->authorizationRequest->profile)
            ->setPurpose(Purpose::TOPUP)
            ->setRequest($request)
            ->verify();

        $validator = (new ExtensionValidator())->setFile($request->file('file'));
        $data_validator = (new DataFormatValidator())->setAgent($agent)->setRequest($request);
        $validator->linkWith(new SheetNameValidator())->linkWith($data_validator);
        $validator->check();

        $bulk_request = $this->storeBulkRequest($agent, $data_validator->getBulkExcelCdnFilePath());

        $data = $data_validator->getData();
        $total = $data_validator->getTotal();
        $file_path = $data_validator->getFilePath();

        $operator_field = TopUpExcel::VENDOR_COLUMN_TITLE;
        $type_field = TopUpExcel::TYPE_COLUMN_TITLE;
        $mobile_field = TopUpExcel::MOBILE_COLUMN_TITLE;
        $amount_field = TopUpExcel::AMOUNT_COLUMN_TITLE;
        $name_field = TopUpExcel::NAME_COLUMN_TITLE;

        $data->each(function ($value, $key) use (
            $creator, $vendor, $agent, $file_path, $top_up_request, $total, $bulk_request,
            $operator_field, $type_field, $mobile_field, $amount_field, $name_field
        ) {
            if (!$value->$operator_field) return;

            $vendor_id = $vendor->getIdByName($value->$operator_field);
            $request = $top_up_request->setType($value->$type_field)
                ->setBulkId($bulk_request->id)
                ->setMobile(BDMobileFormatter::format($value->$mobile_field))
                ->setAmount($value->$amount_field)
                ->setAgent($agent)
                ->setVendorId($vendor_id)
                ->setName($value->$name_field);

            $topup_order = $creator->setTopUpRequest($request)->create();
            if (!$topup_order) return;

            $this->storeBulkRequestNumbers($bulk_request->id, BDMobileFormatter::format($value->$mobile_field), $topup_order->vendor_id);
            if ($top_up_request->hasError()) {
                $sentry = app('sentry');
                $sentry->user_context(['request' => $request->all(), 'message' => $top_up_request->getErrorMessage()]);
                $sentry->captureException(new Exception("Bulk Topup request error"));
                return;
            }

            dispatch(new TopUpExcelJob($agent, $topup_order, $key + 2, $total, $bulk_request));
        });

        unlink($file_path);
        $response_msg = "Your top-up request has been received and will be transferred and notified shortly.";

        return api_response($request, null, 200, ['message' => $response_msg]);
    }

    /**
     * @param $agent
     * @param $bulk_excel_file_path
     * @return TopUpBulkRequest
     */
    public function storeBulkRequest($agent, $bulk_excel_file_path): TopUpBulkRequest
    {
        $topup_bulk_request = new TopUpBulkRequest();
        $topup_bulk_request->agent_id = $agent->id;
        $topup_bulk_request->agent_type = $this->getFullAgentType($agent->type);
        $topup_bulk_request->status = RequestStatus::PENDING;
        $topup_bulk_request->file = $bulk_excel_file_path;
        $this->withCreateModificationField($topup_bulk_request);
        $topup_bulk_request->save();
        return $topup_bulk_request;
    }

    /**
     * @param $type
     * @return string
     */
    private function getFullAgentType($type)
    {
        switch ($type) {
            case 'customer':
                return Customer::class;
            case 'partner':
                return Partner::class;
            case 'affiliate':
                return Affiliate::class;
            case 'business':
            case 'Company':
                return Business::class;
            default:
                return '';
        }
    }

    /**
     * @param $request_id
     * @param $mobile
     * @param $vendor_id
     */
    public function storeBulkRequestNumbers($request_id, $mobile, $vendor_id)
    {
        $topup_bulk_request = new TopUpBulkRequestNumber();
        $topup_bulk_request->topup_bulk_request_id = $request_id;
        $topup_bulk_request->mobile = $mobile;
        $topup_bulk_request->vendor_id = $vendor_id;
        $this->withCreateModificationField($topup_bulk_request);
        $topup_bulk_request->save();
    }

    public function activeBulkTopUps(Request $request)
    {
        $agent = ($this->getFullAgentType($request->type))::find($request->user->id);

        $topup_bulk_requests = TopUpBulkRequest::pending()->agent($agent)
            ->withCount('orders', 'processedOrders')
            ->having('orders_count', '>', 0)
            ->orderBy('id', 'desc')->get()
            ->map(function (TopUpBulkRequest $topup_bulk_request) {
                return [
                    'id' => $topup_bulk_request->id,
                    'agent_id' => $topup_bulk_request->agent_id,
                    'agent_type' => strtolower(class_basename($topup_bulk_request->agent_type)),
                    'status' => $topup_bulk_request->status,
                    'total_numbers' => $topup_bulk_request->orders_count,
                    'total_processed' => $topup_bulk_request->processed_orders_count,
                ];
            })->toArray();

        return response()->json(['code' => 200, 'active_bulk_topups' => $topup_bulk_requests]);
    }

    /**
     * @param Request $request
     * @param TopUpHistoryExcel $history_excel
     * @param TopUpDataFormat $topUp_data_format
     * @param RequestBuilder $request_builder
     * @param TopUpOrderRepository $top_up_order_repo
     * @return JsonResponse
     */
    public function topUpHistory(Request $request, TopUpHistoryExcel $history_excel, TopUpDataFormat $topUp_data_format, RequestBuilder $request_builder, TopUpOrderRepository $top_up_order_repo)
    {
        ini_set('memory_limit', '6096M');
        ini_set('max_execution_time', 480);

        list($offset, $limit) = calculatePagination($request);
        $user = $request->has('partner') ? $request->partner : $request->user;

        $is_excel_report = ($request->has('content_type') && $request->content_type == 'excel');
        if ($is_excel_report) {
            $offset = 0;
            $limit = 10000;
        }

        $request_builder->setOffset($offset)->setLimit($limit)->setAgent($user);
        if ($request->has('from') && $request->from !== "null") {
            $from_date = Carbon::parse($request->from);
            $to_date = Carbon::parse($request->to)->endOfDay();
            $request_builder->setFromDate($from_date)->setToDate($to_date);
        }
        if ($request->has('vendor_id') && $request->vendor_id !== "null") $request_builder->setVendorId($request->vendor_id);
        if ($request->has('status') && $request->status !== "null") $request_builder->setStatus($request->status);
        if ($request->has('q') && $request->q !== "null") $request_builder->setSearchQuery($request->q);
        if ($request->has('connection_type') && $request->connection_type !== "null") $request_builder->setConnectionType($request->connection_type);
        if ($request->has('topup_type') && $request->topup_type == "single") $request_builder->setIsSingleTopup(true);
        if ($request->has('bulk_id') && $request->bulk_id !== "null" && $request->bulk_id) $request_builder->setBulkRequestId($request->bulk_id);

        $total_topups = $top_up_order_repo->getTotalCountByFilter($request_builder);
        $topups = $top_up_order_repo->getByFilter($request_builder);

        list($topup_data, $topup_data_for_excel) = $topUp_data_format->topUpHistoryDataFormat($topups);

        if ($is_excel_report) {
            $history_excel->setAgent($user)->setData($topup_data_for_excel)->takeCompletedAction();
            return api_response($request, null, 200);
        }

        return response()->json([
            'code' => 200,
            'data' => $topup_data,
            'total_topups' => $total_topups,
            'offset' => $offset
        ]);
    }

    /**
     * TOPUP TEST ROUTES
     *
     * @param Request $request
     * @param TopUpRequest $top_up_request
     * @param Creator $creator
     * @return JsonResponse
     * @throws Exception
     */
    public function topUpTest(Request $request, TopUpRequest $top_up_request, Creator $creator)
    {
        $this->validate($request, [
            'mobile' => 'required|string|mobile:bd',
            'connection_type' => 'required|in:prepaid,postpaid',
            'vendor_id' => 'required|exists:topup_vendors,id',
            'amount' => 'required|min:10|max:1000|numeric'
        ]);
        $agent = $request->user;
        $top_up_request->setAmount($request->amount)->setMobile($request->mobile)->setType($request->connection_type)
            ->setAgent($agent)->setVendorId($request->vendor_id);
        if ($top_up_request->hasError()) return api_response($request, null, 403, [
            'message' => $top_up_request->getErrorMessage()
        ]);

        $topup_order = $creator->setTopUpRequest($top_up_request)->create();
        if (!$topup_order) return api_response($request, null, 500);

        /** @var TopUpRechargeManager $topUp */
        $topUp = app(TopUpRechargeManager::class);
        $topUp->setTopUpOrder($topup_order)->recharge();
        return api_response($request, null, 200, [
            'message' => "Recharge Request Successful",
            'id' => $topup_order->id
        ]);
    }

    /**
     * @param Request $request
     * @param OtfAmount $otf_amount
     * @return JsonResponse
     * @throws Exception
     */
    public function specialAmount(Request $request, OtfAmount $otf_amount)
    {
        $special_amount = $otf_amount->get();
        return api_response($request, null, 200, ['otf_lists' => $special_amount]);
    }

    /**
     * @throws \Sheba\TopUp\Exception\UnauthorizedTokenCreationException
     */
    public function generateJwt(Request $request)
    {
        $this->validate($request, [
            'authorization_code' => 'required|string'
        ]);

        /** @var AuthUser $user */
        $user = $request->auth_user;
        $token = $this->tokenManager->setPartner($user->getPartner())->generate($request->authorization_code);

        return api_response($request, null, 200, [
            'topup_token' => $token
        ]);
    }

    /**
     * @throws InvalidTopUpTokenException
     */
    public function checkJwt(Request $request)
    {
        $this->validate($request, [
            'topup_token' => 'required|string'
        ]);

        /** @var AuthUser $auth_user */
        $auth_user = $request->auth_user;
        $this->tokenManager->setPartner($auth_user->getPartner())->validate($request->topup_token);
        return api_response($request, null, 200, ['message' => 'Token is valid']);
    }

    /**
     * @param Request $request
     * @param TopUpBulkRequestFormatter $topup_formatter
     * @return JsonResponse
     */
    public function bulkList(Request $request, TopUpBulkRequestFormatter $topup_formatter)
    {
        $auth_user = $request->auth_user;
        $agent = $auth_user->getBusiness();
        $agent_type = $this->getFullAgentType($agent->type);
        $bulk_topup_data = $topup_formatter->setAgent($agent)->setAgentType($agent_type)->format();

        return api_response($request, null, 200, ['code' => 200, 'data' => $bulk_topup_data]);
    }

    /**
     * @param Request $request
     * @param TopUpDataFormat $topUp_data_format
     * @param TopUpOrderRepository $top_up_order_repo
     * @return JsonResponse
     */
    public function allTopUps(Request $request, TopUpDataFormat $topUp_data_format, TopUpOrderRepository $top_up_order_repo)
    {
        $user = $request->has('partner') ? $request->partner : $request->user;
        $all_topups = $top_up_order_repo->getAllTopUps($user);
        $top_up_data = $topUp_data_format->allTopUpDataFormat($all_topups);

        return response()->json([
            'code' => 200,
            'data' => $top_up_data,
        ]);
    }

    /**
     * @param Request $request
     * @param $user
     * @return Affiliate|Business|Partner
     */
    private function getAgent(Request $request, $user)
    {
        /** @var AuthUser $auth_user */
        $auth_user = $request->auth_user;
        if ($user == 'business') $agent = $auth_user->getBusiness();
        elseif ($user == 'affiliate') $agent = $auth_user->getAffiliate();
        elseif ($user == 'partner') $agent = $auth_user->getPartner();
        else $agent = $request->user;

        return $agent;
    }
}
