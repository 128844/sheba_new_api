<?php

namespace App\Http\Controllers;

use App\Library\PortWallet;
use App\Models\Customer;
use App\Models\Order;
use App\Repositories\JobServiceRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\OrderRepository;
use App\Repositories\SmsHandler;
use App\Sheba\Checkout\Checkout;
use App\Sheba\Checkout\OnlinePayment;
use Illuminate\Http\Request;
use Redis;
use DB;

class OrderController extends Controller
{
    private $orderRepository;
    private $jobServiceRepository;
    private $job_statuses_show;

    public function __construct()
    {
        $this->orderRepository = new OrderRepository();
        $this->jobServiceRepository = new JobServiceRepository();
        $this->job_statuses_show = config('constants.JOB_STATUSES_SHOW');
    }

    public function getNotClosedOrderInfo($customer, Request $request)
    {
        $customer = $request->customer;
        $orders = $this->orderRepository->getOrderInfo($customer);
        $final_orders = [];
        foreach ($orders as $key => $order) {
            $order->calculate();
            if (in_array($order->status, ['Cancelled']) || ($order->status == 'Closed' && $order->due <= 0)) {
                continue;
            }
            foreach ($order->partner_orders as $partner_order) {
                if ($partner_order->status == 'Cancelled' && count($partner_order->jobs) == 1) {
                    $job = $partner_order->jobs[0];
                    if ($job->partnerChangeLog != null) {
                        array_add($partner_order, 'show', false);
                        array_forget($partner_order, 'partner_collection');
                        array_forget($partner_order, 'sheba_collection');
                        array_forget($partner_order->partner, 'categories');
                        array_forget($job, 'partnerChangeLog');
                        continue;
                    }
                } else {
                    array_add($partner_order, 'show', true);
                }
                foreach ($partner_order->jobs as $job) {
                    if ($job->status == "Cancelled") {
                        if ($job->partnerChangeLog != null) {
                            array_add($job, 'show', false);
                            array_forget($partner_order, 'partner_collection');
                            array_forget($partner_order, 'sheba_collection');
                            array_forget($partner_order->partner, 'categories');
                            array_forget($job, 'partnerChangeLog');
                            continue;
                        } else {
                            array_add($job, 'show', true);
                        }
                    }
                    $job['code'] = $job->fullCode();
                    array_add($job, 'customer_charge', $job->grossPrice);
                    array_add($job, 'material_price', $job->materialPrice);
                    array_forget($job, 'partner_order');
                }
                array_add($partner_order, 'total_amount', $partner_order->grossAmount);
                array_add($partner_order, 'paid_amount', $partner_order->paid);
                array_add($partner_order, 'due_amount', $partner_order->due);
                array_add($partner_order, 'total_price', (double)$partner_order->totalPrice);
                array_add($partner_order, 'rounding_cut_off', (double)$partner_order->roundingCutOff);
                array_forget($partner_order, 'partner_collection');
                array_forget($partner_order, 'sheba_collection');
                array_forget($partner_order->partner, 'categories');
            }
            array_add($order, 'total_cost', $order->totalPrice);
            array_add($order, 'due_amount', $order->due);
            array_add($order, 'order_code', $order->code());
            array_push($final_orders, $order);
        }
        return response()->json(['orders' => $final_orders, 'code' => 200, 'msg' => 'successful']);
    }

    public function getClosedOrderInfo($customer, Request $request)
    {
        $customer = $request->customer;
        $orders = $this->orderRepository->getOrderInfo($customer);
        $final_orders = [];
        foreach ($orders as $key => $order) {
            $order->calculate();
            if (in_array($order->status, ['Open', 'Process', 'Cancelled']) || ($order->status == 'Closed' && $order->due != 0)) {
                continue;
            }
            foreach ($order->partner_orders as $partner_order) {
                array_add($partner_order, 'show', true);
                array_add($partner_order, 'total_amount', $partner_order->grossAmount);
                array_add($partner_order, 'paid_amount', $partner_order->paid);
                array_add($partner_order, 'due_amount', $partner_order->due);
                array_add($partner_order, 'total_price', (double)$partner_order->totalPrice);
                array_add($partner_order, 'rounding_cut_off', $partner_order->roundingCutOff);
                $job_partner_change = 0;
                foreach ($partner_order->jobs as $job) {
                    array_add($job, 'show', true);
                    if ($job->status == "Cancelled") {
                        if ($job->partnerChangeLog != null) {
                            $job['show'] = false;
                            $job_partner_change++;
                        }
                    }
                    array_add($job, 'customer_charge', $job->grossPrice);
                    array_add($job, 'material_price', $job->materialPrice);
                    array_forget($job, 'partnerChangeLog');
                }
                if (count($partner_order->jobs) == $job_partner_change) {
                    $partner_order['show'] = false;
                }
                array_forget($partner_order, 'partner_collection');
                array_forget($partner_order, 'sheba_collection');
                array_forget($partner_order->partner, 'categories');
            }
            array_add($order, 'total_cost', $order->totalPrice);
            array_add($order, 'due_amount', $order->due);
            array_add($order, 'order_code', $order->code());
            array_push($final_orders, $order);
        }
        return response()->json(['orders' => $final_orders, 'code' => 200, 'msg' => 'successful']);
    }

    public function getCancelledOrders($customer, Request $request)
    {
        $customer = $request->customer;
        $orders = $this->orderRepository->getOrderInfo($customer);
        $final_orders = [];
        foreach ($orders as $key => $order) {
            $order->calculate();
            if (in_array($order->status, ['Open', 'Process', 'Closed'])) {
                continue;
            }
            foreach ($order->partner_orders as $partner_order) {
                array_add($partner_order, 'show', true);
                array_add($partner_order, 'total_amount', $partner_order->grossAmount);
                array_add($partner_order, 'paid_amount', $partner_order->paid);
                array_add($partner_order, 'due_amount', $partner_order->due);
                array_add($partner_order, 'rounding_cut_off', $partner_order->roundingCutOff);
                $job_partner_change = 0;
                foreach ($partner_order->jobs as $job) {
                    array_add($job, 'show', true);
                    if ($job->status == "Cancelled") {
                        if ($job->partnerChangeLog != null) {
                            $job['show'] = false;
                            $job_partner_change++;
                        }
                    }
                    array_add($job, 'customer_charge', $job->grossPrice);
                    array_add($job, 'material_price', $job->materialPrice);
                    array_forget($job, 'partnerChangeLog');
                }
                if (count($partner_order->jobs) == $job_partner_change) {
                    $partner_order['show'] = false;
                }
                array_forget($partner_order, 'partner_collection');
                array_forget($partner_order, 'sheba_collection');
                array_forget($partner_order->partner, 'categories');
            }
            array_add($order, 'total_cost', $order->totalPrice);
            array_add($order, 'due_amount', $order->due);
            array_add($order, 'order_code', $order->code());
            array_push($final_orders, $order);
        }
        return response()->json(['orders' => $final_orders, 'code' => 200, 'msg' => 'successful']);
    }

    public function checkOrderValidity(Request $request)
    {
        try {
            $key = Redis::get($request->s_token);
            if ($key != null) {
                Redis::del($request->s_token);
                return api_response($request, null, 200);
            } else {
                return api_response($request, null, 404);
            }
        } catch (\Throwable $e) {
            return api_response($request, null, 500);
        }
    }

    public function store($customer, Request $request)
    {
        try {
            $customer = $request->customer;
            $order = new Checkout($customer);
            $order = $order->placeOrder($request);
            if ($order) {
                if ($order->voucher_id != null) {
                    $this->updateVouchers($order, $customer);
                }
                $link = null;
                if ($request->payment_method == 'online') {
                    $link = (new OnlinePayment())->generatePortWalletLink($order->partnerOrders[0], 1);
                }
                $customer = ($customer instanceof Customer) ? $customer : Customer::find($customer);
                (new SmsHandler('order-created'))->send($customer->profile->mobile, [
                    'order_code' => $order->code()
                ]);
                (new NotificationRepository())->send($order);
                return api_response($request, $order, 200, ['link' => $link]);
            }
            return api_response($request, $order, 500);
        } catch (\Throwable $e) {
            return api_response($request, null, 500);
        }
    }

    private function updateVouchers($order, Customer $customer)
    {
        if ($order->voucher_id != null) {
            $voucher = $order->voucher;
            $this->updateVoucherInPromoList($customer, $voucher, $order);
        }
    }

    private function updateVoucherInPromoList(Customer $customer, $voucher, $order)
    {
        $rules = json_decode($voucher->rules);
        if (array_key_exists('nth_orders', $rules) && !array_key_exists('ignore_nth_orders_if_used', $rules)) {
            $nth_orders = $rules->nth_orders;
            if ($customer->orders->count() == max($nth_orders)) {
                $customer->promotions()->where('voucher_id', $order->voucher_id)->update(['is_valid' => 0]);
                return;
            }
        }
        if ($voucher->usage($customer->id) == $voucher->max_order) {
            $customer->promotions()->where('voucher_id', $order->voucher_id)->update(['is_valid' => 0]);
            return;
        }
    }


    public function clearPayment(Request $request)
    {
        try {
            $redis_key_name = 'portwallet-payment-' . $request->invoice;
            $redis_key = Redis::get($redis_key_name);
            if ($redis_key) {
                $data = json_decode($redis_key);
                $response = (new OnlinePayment())->pay($data, $request);
                if ($response['success']) {
                    Redis::del($redis_key_name);
                    return redirect($response['redirect_link']);
                } else {
                    return redirect(env('SHEBA_FRONT_END_URL'));
                }
            }
        } catch (\Throwable $e) {
            return api_response($request, null, 500);
        }
    }

}
