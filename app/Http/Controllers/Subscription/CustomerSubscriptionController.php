<?php

namespace App\Http\Controllers\Subscription;

use App\Exceptions\HyperLocationNotFoundException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sheba\Checkout\Requests\PartnerListRequest;
use Sheba\Checkout\Requests\SubscriptionOrderRequest;
use Sheba\Checkout\SubscriptionOrder;
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

    public function placeSubscriptionRequest(Request $request, SubscriptionOrderRequest $subscriptionOrderRequest, SubscriptionOrder $subscriptionOrder)
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
                'payment_method' => 'required|string|in:online,bkash,wallet',
            ]);
            $subscription_order = \App\Models\SubscriptionOrder::find((int)$subscription);
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

    public function getSubscriptionLists(Request $request)
    {
        $subscription_orders = [
            [
                'id' => 1,
                "name" =>  "Appliances Repair",
                "app_thumb" =>  "https://s3.ap-south-1.amazonaws.com/cdn-shebadev/images/bulk/jpg/Services/983/150.jpg",
                "billing_cycle" =>  "weekly",
                "subscription_period" =>  "Feb 01 -  Feb 07",
                "completed_orders" =>  12,
                "is_active" => 1,
                "partner" =>
                    [
                        "id" => 3,
                        "name" =>  "ETC Service Solutions",
                        "logo" =>  "https://s3.ap-south-1.amazonaws.com/cdn-shebaxyz/images/partners/logos/1528259377_gowala.png",
                    ]
                ],
            [
                'id' => 3,
                "name" =>  "Pure Milk",
                "app_thumb" =>  "https://s3.ap-south-1.amazonaws.com/cdn-shebadev/images/bulk/jpg/Services/983/150.jpg",
                "billing_cycle" =>  "weekly",
                "subscription_period" =>  "Feb 01 -  Feb 07",
                "completed_orders" =>  12,
                "is_active" => 0,
                "partner" =>
                    [
                        "id" => 2336,
                        "name" =>  "Gowala",
                        "logo" =>  "https://s3.ap-south-1.amazonaws.com/cdn-shebaxyz/images/partners/logos/1528259377_gowala.png",
                    ]
                ]
        ];

        return api_response($request, $subscription_orders, 200, ['subscription_orders' => $subscription_orders]);
        try {
            $partner = $request->has('partner') ? $request->partner : null;
            $request->merge(['date' => json_decode($request->date)]);
            #$partnerListRequest->setRequest($request)->prepareObject();
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

    public function getSubscriptionOrderDetails(Request $request)
    {


        $subscription_order_details = [
            [
                'service_id' => 3,
                "service_name" =>  "Pure Milk",
                "app_thumb" =>  "https://s3.ap-south-1.amazonaws.com/cdn-shebadev/images/bulk/jpg/Services/983/150.jpg",
                'partner_id' => 2097,
                'logo' => "https://s3.ap-south-1.amazonaws.com/cdn-shebaxyz/images/partners/logos/1527658222_khaas_food.png",
                'partner_name' => "Khaas Food",
                "subscription_details" =>
                    [
                        "type" => "monthly",
                        "subscription_period" =>  "Feb 01 -  Feb 07",
                        "frequency " => "12 Orders/Month Current",
                        "subscription_fee " => "$2,500",
                        "preferred_time" => "10.00 - 01.00 P.M.",
                        "price" =>  200,
                        "discount" => 20,
                        "total" => 180,
                        "paid_on" => "15 - Feb, 2019"
                    ]
                ],
            [
                'service_id' => 3,
                "service_name" =>  "Pure Milk",
                "app_thumb" =>  "https://s3.ap-south-1.amazonaws.com/cdn-shebadev/images/bulk/jpg/Services/983/150.jpg",
                'partner_id' => 2097,
                'logo' => "https://s3.ap-south-1.amazonaws.com/cdn-shebaxyz/images/partners/logos/1527658222_khaas_food.png",
                'partner_name' => "Khaas Food",
                "subscription_details" =>
                    [
                        "type" => "monthly",
                        "subscription_period" =>  "Feb 01 -  Feb 07",
                        "frequency " => "12 Orders/Month Current",
                        "subscription_fee " => "$2,500",
                        "preferred_time" => "10.00 - 01.00 P.M.",
                        "price" =>  200,
                        "discount" => 20,
                        "total" => 180,
                        "paid_on" => "15 - Feb, 2019"
                    ]
            ],
        ];

        return api_response($request, $subscription_order_details, 200, ['subscription_order_details' => $subscription_order_details]);
        try {
            $partner = $request->has('partner') ? $request->partner : null;
            $request->merge(['date' => json_decode($request->date)]);
            #$partnerListRequest->setRequest($request)->prepareObject();
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
}