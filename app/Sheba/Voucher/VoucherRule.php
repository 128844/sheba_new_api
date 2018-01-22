<?php namespace Sheba\Voucher;


use App\Models\Customer;
use App\Models\Voucher;

class VoucherRule
{
    private $rules;
    public $invalidMessage;
    public $errors;

    public function __construct($rules)
    {
        $this->rules = json_decode($rules);
        $this->errors = [];
    }

    public function hasKey($key)
    {
        return in_array($key, array_keys((array)$this->rules));
    }

    public function checkPartnerService($id)
    {
        if(!$this->hasKey('partner_service')) return true;

        if(in_array($id, $this->rules->partner_service)) return true;

        $this->invalidMessage = $this->invalidMessages('partner_service');
        array_push($this->errors, 'partner_service');
        return false;
    }

    public function checkLocation($id)
    {
        if(!$this->hasKey('locations')) return true;

        if(in_array($id, $this->rules->locations)) return true;

        $this->invalidMessage = $this->invalidMessages('locations');
        array_push($this->errors, 'locations');
        return false;
    }

    public function checkPartner($id)
    {
        if(!$this->hasKey('partners')) return true;

        if(in_array($id, $this->rules->partners)) return true;

        $this->invalidMessage = $this->invalidMessages('partners');
        array_push($this->errors, 'partners');
        return false;
    }

    public function checkCategoryPartner($id)
    {
        if(!$this->hasKey('category_partner')) return true;

        if(in_array($id, $this->rules->category_partner)) return true;

        $this->invalidMessage = $this->invalidMessages('partner_service');
        array_push($this->errors, 'category_partner');
        return false;
    }

    public function checkCustomer($customer)
    {
        return $this->checkCustomerMobile($customer->mobile) && $this->checkCustomerId($customer->id);
    }

    public function checkCustomerMobile($mobile)
    {
        if(!$this->hasKey('customers')) return true;

        if(in_array($mobile, $this->rules->customers)) return true;

        $this->invalidMessage = $this->invalidMessages('customers');
        array_push($this->errors, 'customers');
        return false;
    }

    public function checkCustomerId($id)
    {
        if(!$this->hasKey('customer_ids')) return true;

        if(in_array($id, $this->rules->customer_ids)) return true;

        $this->invalidMessage = $this->invalidMessages('customers');
        array_push($this->errors, 'customers');
        return false;
    }

    public function checkCustomerNthOrder(Customer $customer, Voucher $voucher, $n)
    {
        if(!$this->hasKey('nth_orders')) return true;

        if(in_array($n, $this->rules->nth_orders)) return true;

        if($this->checkForIgnoringNthOrder($customer, $voucher)) return true;

        $this->invalidMessage = $this->invalidMessages('customers');
        array_push($this->errors, 'nth_orders');
        return false;
    }

    public function checkOrderAmount($amount)
    {
        if(!$this->hasKey('order_amount')) return true;

        if($amount >= $this->rules->order_amount) return true;

        $this->invalidMessage = $this->invalidMessages('customers');
        array_push($this->errors, 'order_amount');
        return false;
    }

    public function checkSalesChannel($sales_channel)
    {
        if(!$this->hasKey('sales_channels')) return true;

        if(in_array($sales_channel, $this->rules->sales_channels)) return true;

        $this->invalidMessage = $this->invalidMessages('sales_channels');
        array_push($this->errors, 'sales_channels');
        return false;
    }

    public function invalidMessages($key)
    {
        $general_message = "This code is not valid. ";
        $messages = [
            'partner_service' => $general_message . '(For selected service and partner)',
            'partners' => $general_message . '(For selected partner)',
            'locations' => $general_message . '(For selected location)',
            'validity' => $general_message . '(Time Over)',
            'customers' => $general_message . '(For you)',
            'sales_channels' => $general_message . '(For selected channel)',
        ];
        return (array_key_exists($key, $messages)) ? $messages[$key] : $general_message;
    }

    private function checkForIgnoringNthOrder(Customer $customer, Voucher $voucher)
    {
        return $this->hasKey('ignore_nth_orders_if_used') &&
        $this->rules->ignore_nth_orders_if_used &&
        $this->customerNthOrderHasVoucher($customer, $voucher);
    }

    private function customerNthOrderHasVoucher(Customer $customer, Voucher $voucher)
    {
        return in_array($voucher->id, $customer->nthOrders($this->rules->nth_orders)->pluck('voucher_id')->toArray());
    }

}