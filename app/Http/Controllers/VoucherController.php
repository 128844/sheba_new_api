<?php namespace App\Http\Controllers;

use App\Models\PosCustomer;
use App\Models\PosOrder;
use App\Models\PosOrderDiscount;
use App\Models\Voucher;
use App\Transformers\CustomSerializer;
use App\Transformers\VoucherDetailTransformer;
use App\Transformers\VoucherTransformer;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use Sheba\ModificationFields;
use Sheba\Voucher\DTO\Params\CheckParamsForPosOrder;
use Sheba\Voucher\VoucherRule;
use Throwable;

class VoucherController extends Controller
{
    use ModificationFields;

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function dashboard(Request $request)
    {
        try {
            $partner = $request->partner;

            $partner_voucher_query = Voucher::byPartner($partner);
            $total_sale_with_voucher = $this->calculatePartnerWiseSaleByVoucher($partner_voucher_query);
            $latest_vouchers = [];
            $manager = new Manager();
            $manager->setSerializer(new CustomSerializer());

            $data = [
                'total_voucher'     => $partner_voucher_query->count(),
                'active_voucher'    => $partner_voucher_query->valid()->count(),
                'total_sale_with_voucher' => $total_sale_with_voucher
            ];

            $partner_voucher_query->orderBy('id', 'desc')->take(3)->each(function ($voucher) use (&$latest_vouchers, $manager) {
                $resource = new Item($voucher, new VoucherTransformer());
                $voucher = $manager->createData($resource)->toArray();
                array_push($latest_vouchers, $voucher['data']) ;
            });

            return api_response($request, null, 200, ['data' => $data, 'latest_vouchers' => $latest_vouchers]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $partner = $request->partner;
            list($offset, $limit) = calculatePagination($request);
            $partner_voucher_query = Voucher::byPartner($partner);

            $all_voucher_id = $partner_voucher_query->pluck('id')->toArray();
            $used_voucher_id = PosOrder::byVoucher($all_voucher_id)->pluck('voucher_id')->toArray();

            if ($request->has('filter_type')) {
                if ($request->filter_type == "used") $partner_voucher_query->whereIn('id', $used_voucher_id);
                if ($request->filter_type == "valid") $partner_voucher_query->valid();
                if ($request->filter_type == "invalid") $partner_voucher_query->notValid();
            }
            if ($request->has('q') && !empty($request->q))
                $partner_voucher_query = $partner_voucher_query->search($request->q);

            $partner_voucher_query = $partner_voucher_query->skip($offset)->take($limit);

            $vouchers = [];

            $manager = new Manager();
            $manager->setSerializer(new CustomSerializer());
            $partner_voucher_query->orderBy('id', 'desc')->get()->each(function ($voucher) use (&$vouchers, $manager) {
                $resource = new Item($voucher, new VoucherTransformer());
                $voucher = $manager->createData($resource)->toArray();
                array_push($vouchers, $voucher['data']) ;
            });
            return api_response($request, null, 200, ['vouchers' => $vouchers]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param Request $request
     * @param $partner
     * @param Voucher $voucher
     * @return JsonResponse
     */
    public function show(Request $request, $partner, Voucher $voucher)
    {
        try {
            $partner = $request->partner;
            $manager = new Manager();
            $manager->setSerializer(new CustomSerializer());
            $resource = new Item($voucher, new VoucherDetailTransformer());
            $formatted_voucher = $manager->createData($resource)->toArray();
            $formatted_voucher['data']['is_used'] = $voucher->usedCount() ? true : false;
            list($total_sale, $total_discount) = $this->calculateTotalSaleAndDiscountByVoucher($voucher);
            $data = [
                'total_used' => $voucher->usedCount(),
                'total_sale_with_voucher' => $total_sale,
                'total_discount_with_voucher' => $total_discount
            ];
            return api_response($request, null, 200, ['data' => $data, 'voucher' => $formatted_voucher['data']]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $this->validate($request, [
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'code' => 'required|unique:vouchers',
                'modules' => 'required',
                'applicant_types' => 'required'
            ]);

            $partner = $request->partner;
            $this->setModifier($partner);
            $data = [
                'code' => strtoupper($request->code),
                'rules' => $this->buildRules($request)->toJson(),
                'amount' => $request->amount,
                'cap' => $request->cap,
                'is_amount_percentage' => $request->is_amount_percentage,
                'start_date' => Carbon::parse($request->start_date . ' 00:00:00'),
                'end_date' => Carbon::parse($request->end_date . ' 23:59:59'),
                'max_customer' => ($request->has('max_customer') && !empty($request->max_customer)) ? $request->max_customer : null,
                'max_order' => 0,
                'is_created_by_sheba' => 0,
                'sheba_contribution' => 0.00,
                'partner_contribution' => 100.00,
                'owner_type' => get_class($partner),
                'owner_id' => $partner->id,
                'created_by_type' => get_class($partner)
            ];

            $voucher = Voucher::create($this->withCreateModificationField($data));

            return api_response($request, null, 200, ['voucher' => $voucher]);
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
     * @param $partner
     * @param Voucher $voucher
     * @return JsonResponse
     */
    public function update(Request $request, $partner, Voucher $voucher)
    {
        try {
            $partner = $request->partner;
            $this->validate($request, [
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'code' => 'required|unique:vouchers,code,' . $voucher->id,
                'applicant_types' => 'required',
                'modules' => 'required'
            ]);

            $this->setModifier($partner);

            $data = [
                'code' => strtoupper($request->code),
                'rules' => $this->buildRules($request)->toJson(),
                'amount' => $request->amount,
                'cap' => $request->cap,
                'is_amount_percentage' => $request->is_amount_percentage,
                'start_date' => Carbon::parse($request->start_date . ' 00:00:00'),
                'end_date' => Carbon::parse($request->end_date . ' 23:59:59'),
                'max_customer' => ($request->has('max_customer') && !empty($request->max_customer)) ? $request->max_customer : null,
                'is_created_by_sheba' => 0,
                'sheba_contribution' => 0.00,
                'partner_contribution' => 100.00,
                'owner_type' => get_class($partner),
                'owner_id' => $partner->id,
                'created_by_type' => get_class($partner)
            ];

            $voucher->update($this->withUpdateModificationField($data));

            return api_response($request, null, 200, ['voucher' => $voucher]);
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
     * @return VoucherRule
     */
    private function buildRules(Request $request)
    {
        $rule = new VoucherRule();

        $modules = explode(',', $request->modules);
        $applicant_types = explode(',', $request->applicant_types);
        if ($request->has('modules')) $rule->setModules($modules);
        if ($request->has('applicant_types')) $rule->setApplicantTypes($applicant_types);
        if ($request->has('order_amount')) $rule->setOrderAmount($request->order_amount);
        if ($request->has('customers') && !empty($request->customers)) {
            $mobiles = explode(',', $request->customers);
            $rule->setMobiles($mobiles);
        }
        if ($request->has('pos_services')) {
            $pos_services = explode(',', $request->pos_services);
            $rule->setPartnerPosService($pos_services);
        }

        return $rule;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function validateVoucher(Request $request)
    {
        try {
            $pos_customer = $request->pos_customer ? PosCustomer::find($request->pos_customer) : new PosCustomer();
            $pos_order_params = (new CheckParamsForPosOrder());
            $pos_order_params->setOrderAmount($request->amount)->setApplicant($pos_customer)->setPartnerPosService($request->pos_services);
            $result = voucher($request->code)->checkForPosOrder($pos_order_params)->reveal();

            if ($result['is_valid']) {
                $voucher = $result['voucher'];
                $voucher = [
                    'amount' => (double)$result['amount'],
                    'code' => $voucher->code,
                    'id' => $voucher->id,
                    'title' => $voucher->title
                ];

                return api_response($request, null, 200, ['voucher' => $voucher]);
            } else {
                return api_response($request, null, 403, ['message' => 'Invalid Promo']);
            }
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function deactivateVoucher(Request $request,$partner,Voucher $voucher){
        try {
            $partner = $request->partner;
            $this->setModifier($partner);
            $voucher->end_date = Carbon::now();
            $voucher->update();
            return api_response($request, null, 200, ['msg' => 'Promo deactivated successfully']);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param Voucher $voucher
     * @return array
     */
    private function calculateTotalSaleAndDiscountByVoucher(Voucher $voucher)
    {
        $orders_by_voucher = PosOrder::byVoucher($voucher->id)->get();

        $total_sale = 0;
        $order_id = $orders_by_voucher->pluck('id')->toArray();
        $total_discount = PosOrderDiscount::byVoucher($order_id)->sum('amount');

        $orders_by_voucher->each(function ($order) use (&$total_sale) {
            $total_sale += $order->calculate()->getTotalBill();
        });

        return [$total_sale, (double)$total_discount];
    }

    /**
     * @param $partner_voucher_query
     * @return mixed
     */
    private function calculatePartnerWiseSaleByVoucher($partner_voucher_query)
    {

        $voucher_id = $partner_voucher_query->pluck('id')->toArray();
        $orders_by_voucher = PosOrder::byVoucher($voucher_id)->get();

        $orders_by_voucher->each(function ($order) use (&$total_sale) {
            $total_sale += $order->calculate()->getTotalBill();
        });

        return $total_sale;
    }
}