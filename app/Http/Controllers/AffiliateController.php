<?php namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliateTransaction;
use App\Models\Affiliation;
use App\Models\PartnerAffiliation;
use App\Models\PartnerTransaction;
use App\Models\Resource;
use App\Models\Service;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\Profile;
use App\Models\TopUpOrder;
use App\Models\TopUpVendor;
use App\Repositories\AffiliateRepository;
use App\Repositories\FileRepository;
use App\Repositories\LocationRepository;
use App\Sheba\Bondhu\AffiliateHistory;
use App\Sheba\Bondhu\AffiliateStatus;
use App\Sheba\Bondhu\TopUpEarning;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sheba\ModificationFields;
use Sheba\PartnerPayment\PartnerPaymentValidatorFactory;
use Sheba\Reports\ExcelHandler;
use Sheba\TopUp\Jobs\TopUpJob;
use Sheba\Transactions\InvalidTransaction;
use Sheba\Transactions\Registrar;
use Validator;
use DB;
use Illuminate\Support\Facades\Redis;

class AffiliateController extends Controller
{
    use ModificationFields;

    private $fileRepository;
    private $locationRepository;
    private $affiliateRepository;

    public function __construct()
    {
        $this->fileRepository = new FileRepository();
        $this->locationRepository = new LocationRepository();
        $this->affiliateRepository = new AffiliateRepository();
    }

    public function edit($affiliate, Request $request)
    {
        try {
            $except_fields = [];
            if ($request->has('bkash_no')) {
                $mobile = formatMobile(ltrim($request->bkash_no));
                $request->merge(['bkash_no' => $mobile]);
            } else {
                $except_fields = ['bkash_no'];
            }
            $validation_fields = count($except_fields) > 0 ? $request->except($except_fields) : $request->all();
            if ($msg = $this->_validateEdit($validation_fields)) {
                return response()->json(['code' => 500, 'msg' => $msg]);
            }
            $affiliate = Affiliate::find($affiliate);
            if ($request->has('name') || $request->has('address')) {
                /** @var Profile $profile */
                $profile = $affiliate->profile;
                if ($request->has('name')) $profile->name = $request->name;
                if ($request->has('address')) $profile->address = $request->address;
                $profile->update();
            }
            if ($request->has('bkash_no')) {
                $banking_info = $affiliate->banking_info;
                $banking_info->bKash = $mobile;
                $affiliate->banking_info = json_encode($banking_info);
            }
            if ($request->has('geolocation')) {
                $affiliate->geolocation = $request->geolocation;
            }

            return $affiliate->update() ? response()->json(['code' => 200]) : response()->json(['code' => 404]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getStatus($affiliate, Request $request)
    {
        try {
            $affiliate = Affiliate::where('id', $affiliate)
                ->select('verification_status', 'is_suspended', 'ambassador_code', 'is_ambassador', 'is_moderator')
                ->first();
            return $affiliate != null ? response()->json(['code' => 200, 'affiliate' => $affiliate]) : response()->json(['code' => 404, 'msg' => 'Not found!']);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getDashboardInfo($affiliate, Request $request)
    {
        try {
            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::find($affiliate);
            $info = [
                'wallet' => (double)$affiliate->wallet,
                'total_income' => (double)$affiliate->getIncome(),
                'total_service_referred' => $affiliate->affiliations->count(),
                'total_sp_referred' => $affiliate->partnerAffiliations->count(),
                'last_updated' => Carbon::parse($affiliate->updated_at)->format('dS F,g:i A')
            ];
            return api_response($request, $info, 200, ['info' => $info]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function updateProfilePic(Request $request)
    {
        try {
            if ($msg = $this->_validateImage($request)) {
                return response()->json(['code' => 500, 'msg' => $msg]);
            }
            $photo = $request->file('photo');
            $profile = ($request->affiliate)->profile;
            if (basename($profile->pro_pic) != 'default.jpg') {
                $filename = substr($profile->pro_pic, strlen(env('S3_URL')));
                $this->fileRepository->deleteFileFromCDN($filename);
            }
            $filename = $profile->id . '_profile_image_' . Carbon::now()->timestamp . '.' . $photo->extension();
            $profile->pro_pic = $this->fileRepository->uploadToCDN($filename, $request->file('photo'), 'images/profiles/');
            return $profile->update() ? response()->json(['code' => 200, 'picture' => $profile->pro_pic]) : response()->json(['code' => 404]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }

    }

    public function getWallet($affiliate, Request $request)
    {
        $affiliate = Affiliate::find($affiliate);
        return $affiliate != null ? response()->json(['code' => 200, 'wallet' => $affiliate->wallet]) : response()->json(['code' => 404, 'msg' => 'Not found!']);
    }

    public function leadInfo($affiliate, AffiliateStatus $status, Request $request)
    {
        $rules = [
            'filter_type' => 'required|string',
            'from' => 'required_if:filter_type,date_range',
            'to' => 'required_if:filter_type,date_range',
            'sp_type' => 'required|in:affiliates,partner_affiliates,lite',
            'agent_id' => 'numeric'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $error = $validator->errors()->all()[0];
            return api_response($request, $error, 400, ['msg' => $error]);
        }
        if ((int)$request->agent_data)
            $status = $status->setType($request->sp_type)->getFormattedDate($request)->getAgentsData($affiliate);
        else
            $status = $status->setType($request->sp_type)->getFormattedDate($request)->getIndividualData($request->agent_id);

        return response()->json(['code' => 200, 'data' => $status]);
    }

    public function getAmbassador($affiliate, Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string'
            ]);
            if ($validator->fails()) {
                $error = $validator->errors()->all()[0];
                return api_response($request, $error, 400, ['msg' => $error]);
            }
            $ambassador = Affiliate::with(['profile' => function ($q) {
                $q->select('id', 'name', DB::raw('pro_pic as picture'));
            }])->where([
                ['ambassador_code', strtoupper(trim($request->code))],
                ['is_ambassador', 1]
            ])->first();
            if ($ambassador) {
                return api_response($request, $ambassador->profile, 200, ['info' => $ambassador->profile]);
            } else {
                return api_response($request, null, 404);
            }
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function joinClan($affiliate, Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string'
            ]);
            if ($validator->fails()) {
                $error = $validator->errors()->all()[0];
                return api_response($request, $error, 400, ['msg' => $error]);
            }
            $affiliate = $request->affiliate;
            if ($affiliate->is_ambassador == 1 || $affiliate->ambassador_id != null) {
                return api_response($request, null, 403);
            }
            $ambassador = Affiliate::where([
                ['ambassador_code', strtoupper(trim($request->code))],
                ['id', '<>', $affiliate->id],
                ['is_ambassador', 1]
            ])->first();
            if ($ambassador) {
                $affiliate = $request->affiliate;
                $affiliate->ambassador_id = $ambassador->id;
                $affiliate->under_ambassador_since = Carbon::now();
                $affiliate->update();
                return api_response($request, $ambassador, 200);
            } else {
                return api_response($request, null, 404);
            }
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getAgents($affiliate, Request $request)
    {
        $affiliate = $request->affiliate;
        try {
            if ($affiliate->is_ambassador == 0) {
                return api_response($request, null, 403);
            }
            $q = $request->get('query');
            $range = $request->get('range');
            $sort_order = $request->get('sort_order');

            list($offset, $limit) = calculatePagination($request);
            if (empty($range)) {
                $query[] = $this->allAgents($request->affiliate);
            }
            $query[] = Affiliate::agentsWithFilter($request, 'affiliations')->get()->toArray();
            $query[] = Affiliate::agentsWithFilter($request, 'partner_affiliations')->get()->toArray();

            $agents = $this->mapAgents($query)->where('ambassador_id', $affiliate->id);

            $agents = $this->filterAgents($q, $agents);

            $agents = $this->sortAgents($sort_order, $agents);

            $agents = $agents->splice($offset, $limit)->toArray();
            if (count($agents) > 0) {
                $response = ['agents' => $agents];
                if ($range) {
                    $r_d = getRangeFormat($request);
                    $response['range'] = ['to' => $r_d[0], 'from' => $r_d[1]];
                }
                return api_response($request, $agents, 200, $response);
            }
            return api_response($request, null, 404);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    private function mapAgents($query)
    {
        return collect(array_merge(...$query))->groupBy('id')
            ->map(function ($data) {
                $dataSet = $data[0];
                if (isset($data[1])) {
                    $dataSet['total_gifted_amount'] += $data[1]['total_gifted_amount'];
                    $dataSet['total_gifted_number'] += $data[1]['total_gifted_number'];
                }
                if (isset($data[2])) {
                    $dataSet['total_gifted_amount'] += $data[2]['total_gifted_amount'];
                    $dataSet['total_gifted_number'] += $data[2]['total_gifted_number'];
                }
                return $dataSet;
            })->values();
    }

    private function filterAgents($q, $agents)
    {
        if (isset($q) & !empty($q)) {
            return $agents->filter(function ($data) use ($q) {
                return str_contains($data['name'], $q);
            });
        }
        return $agents;
    }

    private function allAgents($affiliate)
    {
        return $affiliate->agents->map(function ($agent) {
            return [
                'id' => $agent->id,
                'profile_id' => $agent->profile_id,
                'name' => $agent->profile->name,
                'ambassador_id' => $agent->ambassador_id,
                'picture' => $agent->profile->pro_pic,
                'mobile' => $agent->profile->mobile,
                'created_at' => $agent->created_at->toDateTimeString(),
                'joined' => $agent->joined,
                'total_gifted_amount' => 0,
                'total_gifted_number' => 0
            ];
        })->toArray();
    }

    private function sortAgents($sort_order, $agents)
    {
        if (isset($sort_order)) {
            return ($sort_order == 'asc') ? $agents->sortBy('total_gifted_amount') : $agents->sortByDesc('total_gifted_amount');
        }
        return $agents;
    }

    public function getGodFather($affiliate, Request $request)
    {
        try {
            $affiliate = $request->affiliate;
            if ($affiliate->ambassador_id == null) {
                return api_response($request, null, 404);
            } else {
                $profile = collect($affiliate->ambassador->profile)->only(['name', 'pro_pic', 'mobile'])->all();
                $profile['picture'] = $profile['pro_pic'];
                array_forget($profile, 'pro_pic');
                return api_response($request, $profile, 200, ['info' => $profile]);
            }
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getLeaderboard($affiliate, Request $request)
    {
        try {
            list($offset, $limit) = calculatePagination($request);
            $transactions = AffiliateTransaction::where('affiliation_type', '<>', null)
                ->with(['affiliate' => function ($q) {
                    $q->with(['profile' => function ($q) {
                        $q->select('id', 'name', 'pro_pic');
                    }, 'affiliations' => function ($q) {
                        $q->selectRaw('count(*) as total_reference, affiliate_id')->groupBy('affiliate_id');
                    }, 'partnerAffiliations' => function ($q) {
                        $q->selectRaw('count(*) as total_partner_reference, affiliate_id')->groupBy('affiliate_id');
                    }]);
                }])
                ->selectRaw('sum(amount) as earning_amount, affiliate_id')
                ->where('type', 'Credit')->groupBy('affiliate_id')->orderBy('earning_amount', 'desc')->skip($offset)->take($limit)->get();
            $final = [];
            foreach ($transactions as $transaction) {
                $info['id'] = $transaction->affiliate->id;
                $info['earning_amount'] = (double)$transaction->earning_amount;
                $total_affiliation = $transaction->affiliate->affiliations->first() ? $transaction->affiliate->affiliations->first()->total_reference : 0;
                $total_partner_affiliation = $transaction->affiliate->partnerAffiliations->first() ? $transaction->affiliate->partnerAffiliations->first()->total_partner_reference : 0;
                $info['total_reference'] = $total_affiliation + $total_partner_affiliation;
                $info['name'] = $transaction->affiliate->profile->name;
                $info['picture'] = $transaction->affiliate->profile->pro_pic;
                array_push($final, $info);
            }
            return count($final) != 0 ? api_response($request, $final, 200, ['affiliates' => $final]) : api_response($request, null, 404);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getAmbassadorSummary($affiliate, Request $request)
    {
        try {
            $affiliate = $request->affiliate;
            if ($affiliate->is_ambassador == 0) {
                return api_response($request, null, 403);
            }

            $partner_affiliation_total_amount = DB::select('SELECT SUM(affiliate_transactions.amount) as total_amount FROM affiliate_transactions 
                    LEFT JOIN `partner_affiliations` ON `affiliate_transactions`.`affiliation_id` = `partner_affiliations`.`id` 
                    LEFT JOIN `affiliates` ON `partner_affiliations`.`affiliate_id` = `affiliates`.`id` 
                    WHERE `affiliate_transactions`.`affiliation_type` = \'App\\\Models\\\PartnerAffiliation\'
                    AND affiliate_transactions.affiliate_id = ? AND is_gifted = 1
                    AND affiliates.under_ambassador_since < affiliate_transactions.created_at', [$affiliate->id]
            );
            $total_amount = $partner_affiliation_total_amount[0]->total_amount ?: 0;

            $info = collect();
            $info->put('agent_count', $affiliate->agents->count());
            $info->put('earning_amount', $affiliate->agents->sum('total_gifted_amount') + (double)$total_amount);
            $info->put('total_refer', Affiliation::totalRefer($affiliate->id)->count());
            $info->put('sp_count', PartnerAffiliation::spCount($affiliate->id)->count());
            return api_response($request, $info, 200, ['info' => $info->all()]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function lifeTimeGift($affiliate, $agent_id, Request $request)
    {
        try {
            $affiliate = $request->affiliate;
            if ($affiliate->is_ambassador == 0) {
                return api_response($request, null, 403);
            }
            $info = collect();
            $agent = $request->affiliate->agents()->where('id', $agent_id)->first();
            $sp = Affiliate::agentsWithFilter($request, 'partner_affiliations')->get()->filter(function ($d) use ($agent_id) {
                return $d['id'] == $agent_id;
            })->first();
            $gift_amount = $agent ? $agent->total_gifted_amount : 0;
            $gift_amount += $sp ? $sp->total_gifted_amount : 0;
            $info->put('life_time_gift', $gift_amount);
            return api_response($request, $info, 200, $info->all());
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getTransactions($affiliate, Request $request)
    {
        try {
            list($offset, $limit) = calculatePagination($request);
            $affiliate = $request->affiliate;
            $affiliate->load(['transactions' => function ($q) use ($offset, $limit) {
                $q->select('id', 'affiliate_id', 'affiliation_id', 'type', 'log', 'amount', DB::raw('DATE_FORMAT(created_at, "%M %d, %Y at %h:%i %p") as time'))->orderBy('id', 'desc');
            }]);
            if ($affiliate->transactions != null) {
                $transactions = $affiliate->transactions;
                $credit = $transactions->where('type', 'Credit')->splice($offset, $limit)->values()->all();
                $debit = $transactions->where('type', 'Debit')->splice($offset, $limit)->values()->all();
                return api_response($request, $affiliate->transactions, 200, ['credit' => $credit, 'debit' => $debit]);
            } else {
                return api_response($request, null, 404);
            }
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getNotifications($affiliate, Request $request)
    {
        try {
            list($offset, $limit) = calculatePagination($request);
            $notifications = $request->affiliate->notifications()->select('id', 'title', 'event_type', 'event_id', 'type', 'is_seen', 'created_at')->orderBy('id', 'desc')->skip($offset)->limit($limit)->get();
            if (count($notifications) > 0) {
                $notifications = $notifications->map(function ($notification) {
                    $notification->event_type = str_replace('App\Models\\', "", $notification->event_type);
                    array_add($notification, 'timestamp', $notification->created_at->timestamp);
                    return $notification;
                });
                return api_response($request, $notifications, 200, ['notifications' => $notifications]);
            } else {
                return api_response($request, null, 404);
            }
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function rechargeWallet($affiliate, Request $request)
    {
        try {
            $this->validate($request, [
                'transaction_id' => 'required|string',
                'type' => 'required|in:bkash',
            ]);

            /*if ($request->ip() != "103.4.146.66") {
                return api_response($request, null, 500, ['message' => "Temporary Recharge Off"]);
            }*/

            $affiliate = $request->affiliate;
            $transaction = (new Registrar())->register($affiliate, $request->type, $request->transaction_id);

            $this->setModifier($affiliate);
            $this->recharge($affiliate, $transaction);
            return api_response($request, null, 200, ['message' => "Moneybag refilled."]);
        } catch (InvalidTransaction $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 400, ['message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    private function ifTransactionAlreadyExists($transaction_id)
    {
        return (AffiliateTransaction::where('transaction_details', 'like', "%$transaction_id%")->count() > 0) ||
            (PartnerTransaction::where('transaction_details', 'like', "%$transaction_id%")->count() > 0);
    }

    private function recharge(Affiliate $affiliate, $transaction)
    {
        $data = $this->makeRechargeData($transaction);
        $amount = $transaction['amount'];
        DB::transaction(function () use ($amount, $affiliate, $data) {
            $affiliate->rechargeWallet($amount, $data);
        });
    }

    private function makeRechargeData($transaction)
    {
        return [
            'amount' => $transaction['amount'],
            'transaction_details' => json_encode(
                [
                    'name' => 'Bkash',
                    'details' => [
                        'transaction_id' => $transaction['amount'],
                        'gateway' => 'bkash',
                        'details' => $transaction['details']
                    ]
                ]
            ),
            'type' => 'Credit', 'log' => 'Moneybag Refilled'
        ];
    }

    private function _validateImage($request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|mimes:jpeg,png|max:500'
        ]);
        return $validator->fails() ? $validator->errors()->all()[0] : false;
    }

    private function _validateEdit($request)
    {
        $validator = Validator::make($request, [
            'bkash_no' => 'sometimes|required|string|mobile:bd',
        ], ['mobile' => 'Invalid bKash number!']);
        return $validator->fails() ? $validator->errors()->all()[0] : false;
    }

    public function getServicesInfo($affiliate, Request $request)
    {
        try {
            $services = Service::select('id', 'category_id', 'name', 'description', 'bn_name', 'app_thumb', 'banner', 'min_quantity', 'unit')
                ->publishedForBondhu()->orderByRaw('order_for_bondhu IS NULL, order_for_bondhu')
                ->get()->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'bangla_name' => empty($service->bn_name) ? null : $service->bn_name,
                        'image' => $service->app_thumb,
                        'min_quantity' => $service->min_quantity,
                        'unit' => $service->unit,
                        'category_id' => $service->category_id,
                        'banner' => $service->banner,
                        'description' => $service->description
                    ];
                });
            return api_response($request, $services, 200, ['services' => $services]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function history($affiliate, AffiliateHistory $history, Request $request)
    {
        $rules = [
            'filter_type' => 'required|string',
            'from' => 'required_if:filter_type,date_range',
            'to' => 'required_if:filter_type,date_range',
            'sp_type' => 'required|in:affiliates,partner_affiliates',
            'agent_id' => 'required'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $error = $validator->errors()->all()[0];
            return api_response($request, $error, 400, ['msg' => $error]);
        }
        list($offset, $limit) = calculatePagination($request);
        $historyData = $history->setType($request->sp_type)->getFormattedDate($request)->generateData($affiliate, $request->agent_id)->skip($offset)->take($limit)->get();
        return response()->json(['code' => 200, 'data' => $historyData]);
    }

    public function topUpEarning($affiliate, TopUpEarning $top_up_earning, Request $request)
    {
        $rules = [
            'filter_type' => 'required|string',
            'from' => 'required_if:filter_type,date_range',
            'to' => 'required_if:filter_type,date_range',
            'sp_type' => 'required|in:affiliates,partner_affiliates',
            'agent_id' => 'required'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $error = $validator->errors()->all()[0];
            return api_response($request, $error, 400, ['msg' => $error]);
        }
        if ((int)$request->agent_data)
            $earning = $top_up_earning->setType($request->sp_type)->getFormattedDate($request)->getAgentsData($affiliate);
        else
            $earning = $top_up_earning->setType($request->sp_type)->getFormattedDate($request)->getIndividualData($request->agent_id);
        return response()->json(['code' => 200, 'data' => $earning]);
    }

    public function topUpHistory($affiliate, Request $request)
    {
        try {
            $rules = [
                'from' => 'date_format:Y-m-d',
                'to' => 'date_format:Y-m-d|required_with:from'
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $error = $validator->errors()->all()[0];
                return api_response($request, $error, 400, ['msg' => $error]);
            }

            list($offset, $limit) = calculatePagination($request);
            $topups = Affiliate::find($affiliate)->topups();

            $is_excel_report = ($request->has('content_type') && $request->content_type == 'excel') ? true : false;

            if (isset($request->from) && $request->from !== "null") $topups = $topups->whereBetween('created_at', [$request->from . " 00:00:00", $request->to . " 23:59:59"]);
            if (isset($request->vendor_id) && $request->vendor_id !== "null") $topups = $topups->where('vendor_id', $request->vendor_id);
            if (isset($request->status) && $request->status !== "null") $topups = $topups->where('status', $request->status);
            if (isset($request->q) && $request->q !== "null") $topups = $topups->where('payee_mobile', 'LIKE', '%' . $request->q . '%');

            $total_topups = $topups->count();
            if ($is_excel_report) {
                $offset = 0;
                $limit = 100000;
            }
            $topups = $topups->with('vendor')->skip($offset)->take($limit)->orderBy('created_at', 'desc')->get();

            $topup_data = [];
            $queued_jobs = Redis::lrange('queues:topup', 0, -1);
            $queued_topups = [];

            foreach ($queued_jobs as $queued_job) {
                /** @var TopUpJob $data */
                $data = unserialize(json_decode($queued_job)->data->command);
                if ($data->getAgent()->id == $affiliate) {
                    $topup_request = $data->getTopUpRequest();
                    array_push($queued_topups, [
                        'payee_mobile' => $topup_request->getMobile(),
                        'payee_name' => 'N/A',
                        'amount' => (double)$topup_request->getAmount(),
                        'operator' => $data->getVendor()->name,
                        'status' => config('topup.status.pending')['sheba'],
                        'created_at' => Carbon::now()->format('jS M, Y h:i A'),
                        'created_at_raw' => Carbon::now()->format('Y-m-d h:i:s')
                    ]);
                }
            }

            foreach ($topups as $topup) {
                $topup = [
                    'payee_mobile' => $topup->payee_mobile,
                    'payee_name' => $topup->payee_name ? $topup->payee_name : 'N/A',
                    'amount' => $topup->amount,
                    'operator' => $topup->vendor->name,
                    'status' => $topup->status,
                    'created_at' => $topup->created_at->format('jS M, Y h:i A'),
                    'created_at_raw' => $topup->created_at->format('Y-m-d h:i:s')
                ];
                array_push($topup_data, $topup);
            }

            $topup_data = array_merge($queued_topups, $topup_data);

            if ($is_excel_report) {
                $excel = (new ExcelHandler());
                $excel->setName('Topup History');
                $excel->setViewFile('topup_history');
                $excel->pushData('topup_data', $topup_data);
                $excel->download();
            }

            return response()->json(['code' => 200, 'data' => $topup_data, 'total_topups' => $total_topups, 'offset' => $offset]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getCustomerInfo(Request $request)
    {
        try {
            $this->validate($request, [
                'mobile' => 'required|mobile:bd'
            ]);
            $profile = Profile::where('mobile', '+88' . $request->mobile)->first();
            if (!is_null($profile)) {
                $customer_name = $profile->name;

                return api_response($request, $customer_name, 200, ['name' => $customer_name]);
            }
            return api_response($request, [], 404, ['message' => 'Customer not found.']);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getPartnerInfo(Request $request)
    {
        try {
            $this->validate($request, [
                'partner_id' => 'required|numeric'
            ]);
            $partner = Partner::find($request->partner_id);
            if (!is_null($partner)) {
                $customer_name = $partner->name;

                return api_response($request, $customer_name, 200, ['name' => $customer_name]);
            }
            return api_response($request, [], 404, ['message' => 'Customer not found.']);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getPersonalInformation(Request $request)
    {
        try {
            $this->validate($request, [
                'mobile' => 'required|mobile:bd'
            ]);
            $profile = Profile::where('mobile', '+88' . $request->mobile)->first();
            if (!is_null($profile)) {
                $customer_name = $profile->name;
                $resource = Resource::where('profile_id', $profile->id)->first();
                if ($resource) {
                    $token = $resource->remember_token;
                    $resource_informations = [
                        'name' => $profile->name,
                        'image' => $profile->pro_pic,
                        'resource' => [
                            'id' => $resource->id,
                            'token' => $token
                        ]
                    ];
                    if (count($resource->partners) > 0) {
                        $resource_informations['partner'] = [
                            'id' => $resource->partners[0]->id,
                            'name' => $resource->partners[0]->name,
                        ];
                        if ($resource->partners[0]->geo_informations) {
                            $resource_informations['partner']['lat'] = json_decode($resource->partners[0]->geo_informations)->lat;
                            $resource_informations['partner']['lng'] = json_decode($resource->partners[0]->geo_informations)->lng;
                            $resource_informations['partner']['radius'] = json_decode($resource->partners[0]->geo_informations)->radius;
                        }
                    }
                    return api_response($request, $customer_name, 200, ['data' => $resource_informations]);
                } else {
                    $resource_informations = [
                        'name' => $profile->name,
                        'image' => $profile->pro_pic,
                    ];
                    return api_response($request, $customer_name, 200, ['data' => $resource_informations]);
                }
            }
            return api_response($request, [], 404, null);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
}
