<?php

namespace App\Http\Controllers\Subscription;

use App\Exceptions\HyperLocationNotFoundException;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\SubscriptionOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sheba\Checkout\Requests\PartnerListRequest;
use Sheba\Checkout\Requests\SubscriptionOrderRequest;
use Sheba\Checkout\SubscriptionOrderPlace;
use Sheba\Payment\Adapters\Payable\SubscriptionOrderAdapter;
use Sheba\Payment\ShebaPayment;

class CustomerSubscriptionController extends Controller
{
    public function getPartners(Request $request, PartnerListRequest $partnerListRequest)
    {
        try {
            $this->validate($request, [
                'date' => 'required|string',
                'time' => 'sometimes|required|string',
                'services' => 'required|string',
                'isAvailable' => 'sometimes|required',
                'partner' => 'sometimes|required',
                'lat' => 'required|numeric',
                'lng' => 'required|numeric',
                'subscription_type' => 'required|string',
                'filter' => 'string|in:sheba',
            ]);
            $partner = $request->has('partner') ? $request->partner : null;
            $request->merge(['date' => json_decode($request->date)]);
            $partnerListRequest->setRequest($request)->prepareObject();
            $partner_list = new SubscriptionPartnerList();
            $partner_list->setPartnerListRequest($partnerListRequest)->find($partner);
            if ($partner_list->hasPartners) {
                $partner_list->addPricing();
                $partner_list->addInfo();
                if ($request->has('filter') && $request->filter == 'sheba') {
                    $partner_list->sortByShebaPartnerPriority();
                } else {
                    $partner_list->sortByShebaSelectedCriteria();
                }
                $partners = $partner_list->partners;
                $partners->each(function ($partner, $key) {
                    $partner['rating'] = round($partner->rating, 2);
                    array_forget($partner, 'wallet');
                    array_forget($partner, 'package_id');
                    array_forget($partner, 'geo_informations');
                    removeRelationsAndFields($partner);
                });
                return api_response($request, $partners, 200, ['partners' => $partners->values()->all()]);
            }
            return api_response($request, null, 404, ['message' => 'No partner found.']);
        } catch (HyperLocationNotFoundException $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 400, ['message' => 'Your are out of service area.']);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function placeSubscriptionRequest(Request $request, SubscriptionOrderRequest $subscriptionOrderRequest, SubscriptionOrderPlace $subscriptionOrder)
    {
        try {
            $this->validate($request, [
                'date' => 'required|string',
                'time' => 'sometimes|required|string',
                'services' => 'required|string',
                'partner' => 'required|numeric',
                'address_id' => 'required|numeric',
                'subscription_type' => 'required|string',
                'sales_channel' => 'required|string',
            ]);
            $subscriptionOrderRequest->setRequest($request)->prepareObject();
            $subscriptionOrder = $subscriptionOrder->setSubscriptionRequest($subscriptionOrderRequest)->place();
            return api_response($request, $subscriptionOrder, 200, ['order' => [
                'id' => $subscriptionOrder->id
            ]]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function clearPayment(Request $request, $customer, $subscription)
    {
        try {
            $this->validate($request, [
                'payment_method' => 'required|string|in:online,bkash,wallet,cbl',
            ]);
            $subscription_order = SubscriptionOrder::find((int)$subscription);
            $order_adapter = new SubscriptionOrderAdapter();
            $payable = $order_adapter->setModelForPayable($subscription_order)->getPayable();
            $payment = (new ShebaPayment($request->payment_method))->init($payable);
            return api_response($request, $payment, 200, ['payment' => $payment->getFormattedPayment()]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function index(Request $request, $customer)
    {
        try {
            $customer = $request->customer;
            $subscription_orders_list = collect([]);
            $subscription_orders = SubscriptionOrder::where('customer_id', (int)$customer->id)->get();
            foreach ($subscription_orders as $subscription_order) {
                $served_orders = $subscription_order->orders->map(function ($order) {
                    return $order->partnerOrders->where('cancelled_at', null)->filter(function ($partner_order) {
                        return $partner_order->closed_and_paid_at != null;
                    });
                })->flatten()->count();

                #$schedules = collect(json_decode($subscription_order->schedules));

                $service_details = json_decode($subscription_order->service_details);
                $service_details_breakdown = $service_details->breakdown['0'];
                $service = Service::find((int)$service_details_breakdown->id);

                $orders_list = [
                    'subscription_order_id' => $subscription_order->id,
                    "service_name" => $service->name,
                    "app_thumb" => $service->app_thumb,
                    "billing_cycle" => $subscription_order->billing_cycle,
                    "subscription_period" => Carbon::parse($subscription_order->billing_cycle_start)->format('M j') . ' - ' . Carbon::parse($subscription_order->billing_cycle_end)->format('M j'),
                    "completed_orders" => $served_orders . '/' . $subscription_order->orders->count(),
                    "is_active" => Carbon::parse($subscription_order->billing_cycle_end) >= Carbon::today() ? 1 : 0,
                    "partner" =>
                        [
                            "id" => $subscription_order->partner_id,
                            "name" => $service_details->name,
                            "logo" => $service_details->logo,
                        ]
                ];
                $subscription_orders_list->push($orders_list);
            }
            return api_response($request, $subscription_orders_list, 200, ['subscription_orders_list' => $subscription_orders_list]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function show(Request $request, $customer, $subscription)
    {
        try {
            $customer = $request->customer;
            $subscription_order = SubscriptionOrder::find((int)$subscription);
            $served_orders = $subscription_order->orders->map(function ($order) {
                return $order->partnerOrders->where('cancelled_at', null)->filter(function ($partner_order) {
                    return $partner_order->closed_and_paid_at != null;
                });
            })->flatten()->count();

            $service_details = json_decode($subscription_order->service_details);
            $service_details_breakdown = $service_details->breakdown['0'];
            $service = Service::find((int)$service_details_breakdown->id);
            $schedules = collect(json_decode($subscription_order->schedules));

            $subscription_order_details = [
                'service_id' => $service->id,
                "service_name" => $service->name,
                "app_thumb" => $service->app_thumb,
                "partner_id" => $subscription_order->partner_id,
                "partner_name" => $service_details->name,
                "logo" => $service_details->logo,
                "billing_cycle" => $subscription_order->billing_cycle,
                "subscription_period" => Carbon::parse($subscription_order->billing_cycle_start)->format('M j') . ' - ' . Carbon::parse($subscription_order->billing_cycle_end)->format('M j'),
                "total_orders" => $subscription_order->orders->count(),
                "completed_orders" => $served_orders,
                "preferred_time" => $schedules->first()->time,
                "days_left" => Carbon::today()->diffInDays(Carbon::parse($subscription_order->billing_cycle_end)),

                "subscription_fee " => 2500,
                "price" => 200,
                "discount" => 20,
                "total" => 180,

                "paid_on" => $subscription_order->created_at->format('M-j, Y')
            ];

            return api_response($request, $subscription_order_details, 200, ['subscription_order_details' => $subscription_order_details]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
}