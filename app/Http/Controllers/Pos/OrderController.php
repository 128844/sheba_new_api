<?php namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;

use App\Models\Partner;
use App\Models\PosCustomer;
use App\Models\PosOrder;

use App\Sheba\Payment\Adapters\Payable\PaymentLinkOrderAdapter;
use App\Transformers\CustomSerializer;
use App\Transformers\PosOrderTransformer;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

use League\Fractal\Manager;
use League\Fractal\Resource\Item;

use Sheba\Helpers\TimeFrame;
use Sheba\ModificationFields;

use Sheba\Payment\ShebaPayment;
use Sheba\PaymentLink\PaymentLinkTransformer;
use Sheba\Pos\Jobs\OrderBillEmail;
use Sheba\Pos\Jobs\OrderBillSms;
use Sheba\Pos\Order\Creator;
use Sheba\Pos\Payment\Creator as PaymentCreator;
use Sheba\Pos\Order\QuickCreator;
use Sheba\Pos\Order\RefundNatures\NatureFactory;
use Sheba\Pos\Order\RefundNatures\Natures;
use Sheba\Pos\Order\RefundNatures\RefundNature;
use Sheba\Pos\Order\RefundNatures\ReturnNatures;
use Sheba\Pos\Order\Updater;
use Sheba\Pos\Repositories\PosOrderRepository;
use Sheba\Profile\Creator as ProfileCreator;
use Sheba\Pos\Customer\Creator as PosCustomerCreator;
use Sheba\PaymentLink\Creator as PaymentLinkCreator;
use Sheba\Reports\PdfHandler;
use Sheba\Repositories\PartnerRepository;
use Throwable;

class OrderController extends Controller
{
    use ModificationFields;

    public function index(Request $request)
    {
        ini_set('memory_limit', '2096M');
        try {
            $status = $request->status;
            $partner = $request->partner;
            list($offset, $limit) = calculatePagination($request);

            /** @var PosOrder $orders */
            $orders = PosOrder::with('items.service.discounts', 'customer', 'payments', 'logs', 'partner')
                ->byPartner($partner->id)
                ->orderBy('created_at', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            $final_orders = [];

            foreach ($orders as $index => $order) {
                $order->isRefundable();
                $order_data = $order->calculate();
                $manager = new Manager();
                $manager->setSerializer(new CustomSerializer());
                $resource = new Item($order_data, new PosOrderTransformer());
                $order_formatted = $manager->createData($resource)->toArray()['data'];

                $order_create_date = $order->created_at->format('Y-m-d');

                if (!isset($final_orders[$order_create_date])) $final_orders[$order_create_date] = [];
                if (($status == "null") || !$status || ($status && $order->getPaymentStatus() == $status)) {
                    array_push($final_orders[$order_create_date], $order_formatted);
                }
            }

            $orders_formatted = [];

            $pos_orders_repo = new PosOrderRepository();
            $pos_sales = [];
            foreach (array_keys($final_orders) as $date) {
                $timeFrame = new TimeFrame();
                $timeFrame->forADay(Carbon::parse($date))->getArray();
                $pos_orders = $pos_orders_repo->getCreatedOrdersBetween($timeFrame, $partner);
                $pos_orders->map(function ($pos_order) {
                    /** @var PosOrder $pos_order */
                    $pos_order->sale = $pos_order->getNetBill();
                    $pos_order->paid = $pos_order->getPaid();
                    $pos_order->due  = $pos_order->getDue();
                });
                $pos_sales[$date] = [
                    'total_sale' => $pos_orders->sum('sale'),
                    'total_paid' => $pos_orders->sum('paid'),
                    'total_due'  => $pos_orders->sum('due'),
                ];
            }

            foreach ($final_orders as $key => $value) {
                if (count($value) > 0) {
                    $order_list = [
                        'date' => $key,
                        'total_sale' => $pos_sales[$key]['total_sale'],
                        'total_paid' => $pos_sales[$key]['total_paid'],
                        'total_due' => $pos_sales[$key]['total_due'],
                        'orders' => $value
                    ];
                    array_push($orders_formatted, $order_list);
                }
            }

            return api_response($request, $orders_formatted, 200, ['orders' => $orders_formatted]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request)
    {
        try {
            /** @var PosOrder $order */
            $order = PosOrder::with('items.service.discounts', 'customer', 'payments', 'logs', 'partner')->find($request->order);
            if (!$order) return api_response($request, null, 404, ['msg' => 'Order Not Found']);
            $order->calculate();

            $manager = new Manager();
            $manager->setSerializer(new CustomSerializer());
            $resource = new Item($order, new PosOrderTransformer());
            $order = $manager->createData($resource)->toArray();

            return api_response($request, null, 200, ['order' => $order]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function store($partner, Request $request, Creator $creator, ProfileCreator $profileCreator, PosCustomerCreator $posCustomerCreator, PartnerRepository $partnerRepository, PaymentLinkCreator $paymentLinkCreator, PaymentLinkOrderAdapter $paymentLinkOrderAdapter)
    {
        try {
            $this->validate($request, [
                'services' => 'required|string',
                'paid_amount' => 'sometimes|required|numeric',
                'payment_method' => 'sometimes|required|string|in:' . implode(',', config('pos.payment_method')),
                'customer_name' => 'string',
                'customer_mobile' => 'string',
                'customer_address' => 'string',
                'nPos' => 'numeric',
                'discount' => 'numeric',
                'is_percentage' => 'numeric',
                'previous_order_id' => 'numeric'
            ]);
            $link = null;
            if ($request->manager_resource) {
                $partner = $request->partner;
                $this->setModifier($request->manager_resource);
            } else {
                $partner = $partnerRepository->find((int)$partner);
                $profile = $profileCreator->setMobile($request->customer_mobile)->setName($request->customer_name)->create();
                $partner_pos_customer = $posCustomerCreator->setProfile($profile)->setPartner($partner)->create();
                $pos_customer = $partner_pos_customer->customer;
                $this->setModifier($profile->customer);
                $creator->setCustomer($pos_customer);
            }
            $creator->setPartner($partner)->setData($request->all());
            if ($error = $creator->hasError()) return $error;
            $order = $creator->create();
            $order = $order->calculate();
            if ($partner->wallet >= 1) $this->sendCustomerSms($order);
            $this->sendCustomerEmail($order);
            $order->payment_status = $order->getPaymentStatus();
            $order->client_pos_order_id = $request->client_pos_order_id;
            $order->net_bill = $order->getNetBill();
            if ($request->payment_method == 'payment_link') {
                $paymentLink = $paymentLinkCreator->setAmount($order->net_bill)->setReason("PosOrder ID: $order->id Due payment")->setUserName($partner->name)->setUserId($partner->id)
                    ->setUserType('partner')
                    ->setTargetId($order->id)
                    ->setTargetType('pos_order')->save();
                $transformer = new PaymentLinkTransformer();
                $transformer->setResponse($paymentLink);
                $link = ['link' => $transformer->getLink()];
            }
            $order = ['id' => $order->id, 'payment_status' => $order->payment_status, 'net_bill' => $order->net_bill];
            return api_response($request, null, 200, ['message' => 'Order Created Successfully', 'order' => $order, 'payment' => $link]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    private function sendCustomerSms(PosOrder $order)
    {
        if ($order->customer && $order->customer->profile->mobile)
            dispatch(new OrderBillSms($order));
    }

    private function sendCustomerEmail($order)
    {
        if ($order->customer && $order->customer->profile->email)
            dispatch(new OrderBillEmail($order));
    }

    /**
     * @param Request $request
     * @param QuickCreator $creator
     * @return JsonResponse
     */
    public function quickStore(Request $request, QuickCreator $creator)
    {
        try {
            $this->validate($request, [
                'amount' => 'required|numeric',
                /*'paid_amount' => 'required|numeric',
                'payment_method' => 'required|string|in:' . implode(',', config('pos.payment_method'))*/
            ]);
            $partner = $request->partner;
            $this->setModifier($request->manager_resource);

            $order = $creator->setData($request->all())->create();
            $order = $order->calculate();
            if ($partner->wallet >= 1) $this->sendCustomerSms($order);
            $this->sendCustomerEmail($order);
            $order->payment_status = $order->getPaymentStatus();

            return api_response($request, null, 200, ['msg' => 'Order Created Successfully', 'order' => $order]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param Request $request
     * @param Updater $updater
     * @return JsonResponse
     */
    public function update(Request $request, Updater $updater)
    {
        $this->setModifier($request->manager_resource);

        try {
            /** @var PosOrder $order */
            $order = PosOrder::with('items')->find($request->order);
            $is_returned = ($this->isReturned($order, $request));
            $refund_nature = $is_returned ? Natures::RETURNED : Natures::EXCHANGED;
            $return_nature = $is_returned ? $this->getReturnType($request, $order) : null;

            /** @var RefundNature $refund */
            $refund = NatureFactory::getRefundNature($order, $request->all(), $refund_nature, $return_nature);
            $refund->update();

            $order->payment_status = $order->calculate()->getPaymentStatus();
            return api_response($request, null, 200, ['msg' => 'Order Updated Successfully', 'order' => $order]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param PosOrder $order
     * @param Request $request
     * @return bool
     */
    private function isReturned(PosOrder $order, Request $request)
    {
        $services = $order->items->pluck('service_id')->toArray();
        $request_services = collect(json_decode($request->services, true))->pluck('id')->toArray();

        return $services === $request_services;
    }

    private function getReturnType(Request $request, PosOrder $order)
    {
        $request_services_quantity = collect(json_decode($request->services, true))->pluck('quantity')->toArray();

        $is_full_order_returned = (empty(array_filter($request_services_quantity)));
        $is_item_added = array_sum($request_services_quantity) > $order->items->sum('quantity');

        return $is_full_order_returned ?
            ReturnNatures::FULL_RETURN :
            ($is_item_added ? ReturnNatures::QUANTITY_INCREASE : ReturnNatures::PARTIAL_RETURN);
    }

    /**
     * SMS TO CUSTOMER ABOUT POS ORDER BILLS
     *
     * @param Request $request
     * @param Updater $updater
     * @return JsonResponse
     */
    public function sendSms(Request $request, Updater $updater)
    {
        try {
            $partner = $request->partner;
            $this->setModifier($request->manager_resource);
            /** @var PosOrder $order */
            $order = PosOrder::with('items')->find($request->order)->calculate();

            if ($request->has('customer_id') && is_null($order->customer_id)) {
                $requested_customer = PosCustomer::find($request->customer_id);
                $order = $updater->setOrder($order)->setData(['customer_id' => $requested_customer->id])->update();
            }

            if (!$order) return api_response($request, null, 404, ['msg' => 'Order not found']);
            if (!$order->customer) return api_response($request, null, 404, ['msg' => 'Customer not found']);
            if (!$order->customer->profile->mobile) return api_response($request, null, 404, ['msg' => 'Customer mobile not found']);

            if ($partner->wallet >= 1) {
                dispatch(new OrderBillSms($order));
                return api_response($request, null, 200, ['msg' => 'SMS Send Successfully']);
            } else {
                return api_response($request, null, 404, ['msg' => 'Insufficient Wallet']);
            }
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * EMAIL TO CUSTOMER ABOUT POS ORDER BILLS
     *
     * @param Request $request
     * @param Updater $updater
     * @return JsonResponse
     */
    public function sendEmail(Request $request, Updater $updater)
    {
        try {
            $this->setModifier($request->manager_resource);
            /** @var PosOrder $order */
            $order = PosOrder::with('items')->find($request->order)->calculate();

            if ($request->has('customer_id') && is_null($order->customer_id)) {
                $requested_customer = PosCustomer::find($request->customer_id);
                $order = $updater->setOrder($order)->setData(['customer_id' => $requested_customer->id])->update();
            }

            if (!$order) return api_response($request, null, 404, ['msg' => 'Order not found']);
            if (!$order->customer) return api_response($request, null, 404, ['msg' => 'Customer not found']);
            if (!$order->customer->profile->email) return api_response($request, null, 404, ['msg' => 'Customer email not found']);

            dispatch(new OrderBillEmail($order));
            return api_response($request, null, 200, ['msg' => 'Email Send Successfully']);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function collectPayment(Request $request, PaymentCreator $payment_creator)
    {
        $this->setModifier($request->manager_resource);
        try {
            $this->validate($request, [
                'paid_amount' => 'required|numeric',
                'payment_method' => 'required|string|in:' . implode(',', config('pos.payment_method'))
            ]);

            /** @var PosOrder $order */
            $order = PosOrder::find($request->order);
            $payment_data = [
                'pos_order_id' => $order->id,
                'amount' => $request->paid_amount,
                'method' => $request->payment_method
            ];
            $payment_creator->credit($payment_data);
            $order = $order->calculate();
            $order->payment_status = $order->getPaymentStatus();

            return api_response($request, null, 200, ['msg' => 'Payment Collect Successfully', 'order' => $order]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function downloadInvoice(Request $request, $partner, PosOrder $order)
    {
        try {
            $pdf_handler = new PdfHandler();
            $pos_order = $order->calculate();
            $partner = $pos_order->partner;
            $customer = $pos_order->customer->profile;

            $info = [
                'amount' => $pos_order->getNetBill(),
                'created_at' => $pos_order->created_at->format('jS M, Y, h:i A'),
                'payment_receiver' => [
                    'name'      => $partner->name,
                    'image'     => $partner->logo,
                    'mobile'    => $partner->getContactNumber(),
                    'address'   => $partner->address,
                    'vat_registration_number' => $partner->vat_registration_number
                ],
                'user' => [
                    'name'      => $customer->name,
                    'mobile'    => $customer->mobile
                ],
                'pos_order' => $pos_order ? [
                    'items'     => $pos_order->items,
                    'discount'  => $pos_order->getTotalDiscount(),
                    'total'     => $pos_order->getTotalPrice(),
                    'grand_total' => $pos_order->getTotalBill(),
                    'paid'      => $pos_order->getPaid(),
                    'due'       => $pos_order->getDue(),
                    'status'    => $pos_order->getPaymentStatus(),
                    'vat'       => $pos_order->getTotalVat()] : null
            ];

            $invoice_name = 'pos_order_invoice_' . $pos_order->id;
            return $pdf_handler->setData($info)->setName($invoice_name)->setViewFile('transaction_invoice')->save();
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
}
