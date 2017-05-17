<?php

namespace Sheba\Voucher;

use App\Models\Customer;
use App\Models\Promotion;
use App\Models\Voucher;
use Carbon\Carbon;

class PromotionList
{
    public static function add($customer, $promo)
    {
        $promoList = new PromotionList();
        $voucher = $promoList->isValid($promo);
        if ($voucher != false) {
            $customer = Customer::find($customer);
            if (!$promoList->isAlreadyAdded($voucher, $customer)) {
                return $promoList->create($customer, $voucher);
            }
        } else {
            return false;
        }
    }

    private function isValid($promo)
    {
        $timestamp = Carbon::now();
        $voucher = Voucher::where('code', $promo)
            ->where(function ($query) use ($timestamp) {
                $query->where([
                    ['start_date', '<=', $timestamp],
                    ['end_date', '>=', $timestamp]
                ])->orWhere('is_referral', 1);
            })->first();
        return $voucher != null ? $voucher : false;
    }

    private function isAlreadyAdded($voucher, $customer)
    {
        foreach ($customer->promotions as $promotion) {
            if ($voucher->is_referral == 1) {
                if (count($customer->orders) > 0 || $promotion->voucher->is_referral == 1) {
                    return true;
                }
            } else {
                if ($promotion->voucher->id == $voucher->id) {
                    return true;
                }
            }
        }
        return false;
    }

    private function create($customer, $voucher)
    {
        $promo = new Promotion();
        $promo->customer_id = $customer->id;
        $promo->voucher_id = $voucher->id;
        $promo->is_valid = 1;
        $date = Carbon::now()->addDays(90);
        $promo->valid_till = $date->toDateString() . " 23:59:59";
        return $promo->save() ? $promo : false;
    }
}