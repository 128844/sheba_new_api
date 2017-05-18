<?php namespace Sheba\Voucher;

use App\Models\Customer;
use App\Models\Promotion;
use App\Models\Voucher;

class ReferralCreator
{
    const AMOUNT = 200;
    private $rules = array(
        'sales_channels' => array('Web', 'App'),
        'nth_orders' => [1]
    );

    public function create(Customer $customer, $referred_voucher_id = null)
    {
        $voucher = new Voucher();
        $voucher->code = $customer->generateReferral();
        if ($referred_voucher_id != null) {
            $voucher->referred_from = $referred_voucher_id;
            $this->rules += ['customer_ids' => [Voucher::find($referred_voucher_id)->owner_id]];
        }
        return $this->saveVoucher($customer, $voucher);
    }

    public function saveVoucher($customer, $voucher)
    {
        $voucher->rules = json_encode($this->rules);
        $voucher->title = $this->getIdentity($customer) . " has gifted you " . self::AMOUNT . "tk &#128526;";
        $voucher->amount = self::AMOUNT;
        $voucher->max_order = 1;
        $voucher->sheba_contribution = 100;
        $voucher->owner_type = 'App\Models\Customer';
        $voucher->owner_id = $customer->id;
        $voucher->is_referral = 1;
        if ($voucher->save()) {
            return $voucher;
        }
    }

    private function getIdentity($customer)
    {
        if ($customer->name != '') {
            return $customer->name;
        } elseif ($customer->mobile) {
            return $customer->mobile;
        }
        return $customer->email;
    }
}