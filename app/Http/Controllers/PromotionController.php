<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PartnerService;
use App\Repositories\CartRepository;
use App\Repositories\PartnerServiceRepository;
use App\Sheba\Checkout\Discount;
use App\Sheba\Checkout\PartnerList;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Sheba\Voucher\PromotionList;
use Sheba\Voucher\VoucherSuggester;

class PromotionController extends Controller
{
    public function index($customer, Request $request)
    {
        try {
            $customer = $request->customer->load(['orders', 'promotions' => function ($q) {
                $q->valid()->select('id', 'voucher_id', 'customer_id', 'valid_till')->with(['voucher' => function ($q) {
                    $q->select('id', 'code', 'amount', 'title', 'is_amount_percentage', 'cap', 'max_order');
                }]);
            }]);
            foreach ($customer->promotions as &$promotion) {
                $promotion['valid_till_timestamp'] = $promotion->valid_till->timestamp;
                $promotion['usage_left'] = (int)$promotion->voucher->max_order - $customer->orders->where('voucher_id', $promotion->voucher->id)->count();
            }
            return $customer->promotions->count() > 0 ? api_response($request, $customer->promotions, 200, ['promotions' => $customer->promotions]) : api_response($request, $customer->promotions, 404);
        } catch (\Throwable $e) {
            return api_response($request, null, 500);
        }

    }

    public function addPromo($customer, Request $request)
    {
        $promotion = new PromotionList($request->customer);
        list($promotion, $msg) = $promotion->add($request->promo);
        return $promotion != false ? api_response($request, $promotion, 200, ['promotion' => $promotion]) : api_response($request, null, 404);
    }

    public function getPromo($customer)
    {
        $customer = Customer::with(['promotions' => function ($q) {
            $q->select('id', 'voucher_id', 'customer_id', 'valid_till')->where([
                ['valid_till', '>=', Carbon::now()],
                ['is_valid', 1]
            ])->with(['voucher' => function ($q) {
                $q->select('id', 'code', 'amount', 'title', 'is_amount_percentage', 'cap');
            }]);
        }])->select('id')->where('id', $customer)->first();
        return $customer != null ? response()->json(['code' => 200, 'promotions' => $customer->promotions]) : response()->json(['code' => 404]);
    }

    public function suggestPromo($customer, Request $request, VoucherSuggester $voucherSuggester)
    {
        if ((new CartRepository())->hasDiscount(json_decode($request->cart)->items)) {
            return api_response($request, null, 404, ['result' => 'Discount available for service!']);
        }
        $voucherSuggester->init($request->customer, $request->cart, $request->location, $request->has('sales_channel') ? $request->sales_channel : 'Web');
        $promo = $voucherSuggester->suggest();
        if ($promo != null) {
            return response()->json(['code' => 200, 'amount' => (double)$promo['amount'], 'voucher_code' => $promo['voucher']->code]);
        } else {
            return response()->json(['code' => 404]);
        }
    }

    public function applyPromotion($customer, Request $request, VoucherSuggester $voucherSuggester)
    {
        try {
            $partner_list = new PartnerList(json_decode($request->services), $request->date, $request->time, $request->location);
            $partner_list->find($request->partner);
            if ($partner_list->hasPartners) {
                $partner = $partner_list->partners->first();
                $selected_services = $partner_list->selected_services;
                foreach ($selected_services as &$selected_service) {
                    $service = $partner->services->where('id', $selected_service->id)->first();
                    if ($service->isOptions()) {
                        $price = (new PartnerServiceRepository())->getPriceOfOptionsService($service->pivot->prices, $selected_service->option);
                    } else {
                        $price = (double)$service->pivot->prices;
                    }
                    $discount = new Discount($price, $selected_service->quantity);
                    $discount->calculateServiceDiscount((PartnerService::find($service->pivot->id))->discount());
                    if ($discount->__get('hasDiscount')) {
                        return api_response($request, null, 403);
                    }
                    $service['price'] = $discount->__get('discounted_price');
                }
            } else {
                return api_response($request, null, 400);
            }
            $voucherSuggester->init($request->customer, $selected_services, $partner->id, (int)$request->location, $price, $request->has('sales_channel') ? $request->sales_channel : 'Web');
            $promo = $voucherSuggester->suggest();
            if ($promo != null) {
                return api_response($request, $promo, 200, ['voucher' => array(
                    'amount' => (double)$promo['amount'], 'code' => $promo['voucher']->code
                )]);
            } else {
                return api_response($request, null, 404);
            }
        } catch (\Throwable $e) {
            dd($e);
            return api_response($request, null, 500);
        }
    }
}
