<?php namespace App\Http\Controllers\Pos;

use App\Exceptions\DoNotReportException;
use App\Exceptions\Pos\Customer\PartnerPosCustomerNotFoundException;
use App\Exceptions\Pos\Customer\PosCustomerNotFoundException;
use App\Exceptions\Pos\Order\NotEnoughStockException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentLink\PaymentLinkController;
use App\Models\Partner;
use App\Models\PosCustomer;
use App\Models\PosOrder;
use App\Models\Profile;
use App\Sheba\InventoryService\InventoryServerClient;
use App\Sheba\Pos\Order\Invoice\InvoiceService;
use App\Sheba\PosOrderService\PosOrderServerClient;
use App\Sheba\UserMigration\Modules;
use App\Transformers\CustomSerializer;
use App\Transformers\PosOrderTransformer;
use Dingo\Api\Routing\Helpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use Sheba\Dal\Discount\InvalidDiscountType;
use Sheba\Authentication\Exceptions\AuthenticationFailedException;
use Sheba\AccountingEntry\Exceptions\AccountingEntryServerError;
use Sheba\Dal\POSOrder\OrderStatuses;
use Sheba\Dal\POSOrder\SalesChannels;
use Sheba\DueTracker\Exceptions\UnauthorizedRequestFromExpenseTrackerException;
use Sheba\ExpenseTracker\EntryType;
use Sheba\ExpenseTracker\Exceptions\ExpenseTrackingServerError;
use Sheba\ExpenseTracker\Repository\AutomaticEntryRepository;
use Sheba\ModificationFields;
use Sheba\PartnerStatusAuthentication;
use Sheba\PaymentLink\Creator as PaymentLinkCreator;
use Sheba\PaymentLink\PaymentLinkStatics;
use Sheba\PaymentLink\PaymentLinkTransformer;
use Sheba\Pos\Customer\Creator as PosCustomerCreator;
use Sheba\Pos\Exceptions\InvalidPosOrder;
use Sheba\Pos\Exceptions\PosExpenseCanNotBeDeleted;
use Sheba\Pos\Jobs\OrderBillEmail;
use Sheba\Pos\Jobs\OrderBillSms;
use Sheba\Pos\Jobs\WebstoreOrderSms;
use Sheba\Pos\Notifier\WebstorePushNotificationHandler;
use Sheba\Pos\Order\Creator;
use Sheba\Pos\Order\Deleter as PosOrderDeleter;
use Sheba\Pos\Order\PosOrderList;
use Sheba\Pos\Order\PosOrderResolver;
use Sheba\Pos\Order\QuickCreator;
use Sheba\Pos\Order\RefundNatures\NatureFactory;
use Sheba\Pos\Order\RefundNatures\Natures;
use Sheba\Pos\Order\RefundNatures\RefundNature;
use Sheba\Pos\Order\RefundNatures\ReturnNatures;
use Sheba\Pos\Order\StatusChanger;
use Sheba\Pos\Order\Updater;
use Sheba\Pos\Payment\Creator as PaymentCreator;
use Sheba\Pos\Repositories\PosOrderPaymentRepository;
use Sheba\Pos\Repositories\PosOrderRepository;
use Sheba\Profile\Creator as ProfileCreator;
use Sheba\Reports\Exceptions\NotAssociativeArray;
use Sheba\Repositories\Interfaces\PaymentLinkRepositoryInterface;
use Sheba\Repositories\PartnerRepository;
use Sheba\RequestIdentification;
use Sheba\Reward\ActionRewardDispatcher;
use Sheba\Subscription\Partner\Access\AccessManager;
use Sheba\Subscription\Partner\Access\Exceptions\AccessRestrictedExceptionForPackage;
use Sheba\Transactions\Types;
use Sheba\Transactions\Wallet\WalletDebitForbiddenException;
use Sheba\Transactions\Wallet\WalletTransactionHandler;
use Sheba\Usage\Usage;
use Throwable;

class OrderController extends Controller
{
    use ModificationFields, Helpers;

    /**
     * @var PosOrderServerClient
     */
    private $posOrderServerClient;

    public function __construct(PosOrderServerClient $posOrderServerClient)
    {
        $this->posOrderServerClient = $posOrderServerClient;
    }

    public function index(Request $request, PosOrderList $posOrderList)
    {
        ini_set('memory_limit', '4096M');
        ini_set('max_execution_time', 420);
        $status = $request->status;

        $partner = resolvePartnerFromAuthMiddleware($request);
        list($offset, $limit) = calculatePagination($request);
        $posOrderList = $posOrderList->setPartner($partner)->setStatus($status)->setOffset($offset)->setLimit($limit);
        if ($request->has('sales_channel')) $posOrderList = $posOrderList->setSalesChannel($request->sales_channel);
        if ($request->has('type')) $posOrderList = $posOrderList->setType($request->type);
        if ($request->has('order_status')) $posOrderList = $posOrderList->setOrderStatus($request->order_status);
        if ($request->has('q') && $request->q !== "null") $posOrderList = $posOrderList->setQuery($request->q);
        $orders_formatted = $posOrderList->get();
        return api_response($request, $orders_formatted, 200, ['orders' => $orders_formatted]);
    }


    /**
     * @param Request $request
     * @param PaymentLinkTransformer $payment_link
     * @return JsonResponse
     */
    public function show(Request $request, PaymentLinkTransformer $payment_link)
    {
        /** @var PosOrder $order */
        $order = PosOrder::with('items.service.discounts', 'customer', 'payments', 'logs', 'partner')->withTrashed()->find($request->order);
        if (!$order)
            return api_response($request, null, 404, ['msg' => 'Order Not Found']);
        $order->calculate();
        $manager = new Manager();
        $manager->setSerializer(new CustomSerializer());
        $resource = new Item($order, new PosOrderTransformer());
        $order = $manager->createData($resource)->toArray();

        $order['data']['payment_method'] = empty($order['data']['payments']) ? 'cod' : collect($order['data']['payments'])->where('transaction_type', Types::CREDIT)->sortByDesc('created_at')->first()['method'];

        if (array_key_exists('payment_link_target', $order['data'])) {

            $payment_link_target = $order['data']['payment_link_target'];
            $link = app(PaymentLinkRepositoryInterface::class)->getActivePaymentLinkByPosOrder($payment_link_target);
            if ($link) {
                (new PosOrderTransformer())->addPaymentLinkDataToOrder($order, $link);
                unset($order['data']['payment_link_target']);
            }
        }
        return api_response($request, null, 200, ['order' => $order]);
    }

    /**
     * @param $partner
     * @param Request $request
     * @param Creator $creator
     * @param ProfileCreator $profileCreator
     * @param PosCustomerCreator $posCustomerCreator
     * @param PartnerRepository $partnerRepository
     * @param PaymentLinkCreator $paymentLinkCreator
     * @return array|false|JsonResponse
     * @throws DoNotReportException
     * @throws InvalidDiscountType
     * @throws PartnerPosCustomerNotFoundException
     * @throws PosCustomerNotFoundException
     * @throws NotEnoughStockException
     * @throws AccountingEntryServerError|ExpenseTrackingServerError
     * @return array|false|JsonResponse
     * @return array|JsonResponse
     * @throws ExpenseTrackingServerError
     * @throws NotAssociativeArray
     * @throws DoNotReportException
     * @throws InvalidDiscountType
     * @throws \Sheba\Authentication\Exceptions\AuthenticationFailedException
     * @throws AuthenticationFailedException
     */
    public function store($partner, Request $request, Creator $creator, ProfileCreator $profileCreator, PosCustomerCreator $posCustomerCreator, PartnerRepository $partnerRepository, PaymentLinkCreator $paymentLinkCreator)
    {
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
            'previous_order_id' => 'numeric',
            'emi_month' => 'required_if:payment_method,emi|numeric',
            'amount_without_charge' => 'sometimes|required_if:payment_method,emi|numeric|min:' . config('emi.manager.minimum_emi_amount'),
            'payment_link_amount' => 'sometimes|numeric',
            'sales_channel' => 'sometimes|string'
        ]);
        $link = null;
        if ($request->manager_resource) {
            $partner = $request->partner;
            if($partner->isMigrated(Modules::POS))  return api_response($request, null, 403,["message" =>'অনুগ্রহ করে অ্যাপটি প্লে-স্টোর থেকে আপডেট করুন']);
            $modifier = $request->manager_resource;
            $usage_type = Usage::Partner()::POS_ORDER_CREATE;
            $this->setModifier($modifier);
            $creator->setStatus(OrderStatuses::COMPLETED);
        } else {
            /** @var Partner $partner */
            $partner = $partnerRepository->find((int)$partner);
            if($partner->isMigrated(Modules::POS))  return api_response($request, null, 403,["message" =>'অনুগ্রহ করে অ্যাপটি প্লে-স্টোর থেকে আপডেট করুন']);
            /** @var Profile $profile */
            $profile = $profileCreator->setMobile($request->customer_mobile)->setName($request->customer_name)->create();
            $_data['mobile'] = $request->customer_mobile;
            $_data['name'] = $request->customer_name;
            $partner_pos_customer = $posCustomerCreator->setData($_data)->setProfile($profile)->setPartner($partner)->create();
            $pos_customer = $partner_pos_customer->customer;
            $modifier = $profile->customer;
            $usage_type = Usage::Partner()::PRODUCT_LINK;
            $this->setModifier($modifier);
            $creator->setCustomer($pos_customer);
            $creator->setStatus(OrderStatuses::PENDING);
        }
        $creator->setPartner($partner)->setRequest($request);
        if ($error = $creator->hasDueError())
            return $error;
        /**
         * POS ORDER CHECK IF STOCK LIMIT EXCEED
         *
         * if ($error = $creator->hasError())
         *     return $error;
         */
        $order = $creator->create();
        $order = $order->calculate();
        /**
         * TURNED OFF POS ORDER CREATE SMS BY SERVER END, HANDLED BY CLIENT SIDE
         *
         * if ($partner->wallet >= 1) $this->sendCustomerSms($order);
         */
        try {
            if ($order->sales_channel == SalesChannels::WEBSTORE) {
                if ($partner->is_webstore_sms_active) dispatch(new WebstoreOrderSms($partner, $order->id));
                $this->sendOrderPlacePushNotificationToPartner($order);
            }
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
        }
        $this->sendCustomerEmail($order);
        $order->payment_status = $order->getPaymentStatus();
        $order->client_pos_order_id = $request->client_pos_order_id;
        $order->net_bill = $order->getNetBill();
        $payment_link_amount = $request->has('payment_link_amount') ? $request->payment_link_amount : $order->net_bill;
        if ($request->payment_method == 'payment_link' || $request->payment_method == 'emi') {
            (new PartnerStatusAuthentication())->handleInside($partner);
            $paymentLink = $paymentLinkCreator->setAmount($payment_link_amount)->setReason("PosOrder ID: $order->id Due payment")
                ->setUserName($partner->name)->setUserId($partner->id)
                ->setUserType('partner')
                ->setTargetId($order->id)
                ->setTargetType('pos_order')
                ->setEmiMonth($request->emi_month);
            if ($request->payment_method == 'emi') {
                $paymentLink->setInterest($order->interest)->setBankTransactionCharge($order->bank_transaction_charge);
            }
            if ($order->customer) {
                $paymentLink->setPayerId($order->customer->id)->setPayerType('pos_customer');
            }
            $paymentLink = $paymentLink->save();

            $transformer = new PaymentLinkTransformer();
            $transformer->setResponse($paymentLink);
            $link = ['link' => config('sheba.payment_link_web_url') . '/' . $transformer->getLinkIdentifier()];
        }
        $order = [
            'id' => $order->id,
            'payment_status' => $order->payment_status,
            'net_bill' => $order->net_bill,
            "client_pos_order_id" => $request->has('client_pos_order_id') ? $request->client_pos_order_id : null,
            'partner_wise_order_id' => $order->partner_wise_order_id
        ];
        app()->make(ActionRewardDispatcher::class)->run('pos_order_create', $partner, $partner, $order, (new RequestIdentification())->get()['portal_name']);
        /**
         * USAGE LOG
         */
        (new Usage())->setUser($partner)->setType($usage_type)->create($modifier);
        return api_response($request, null, 200, [
            'message' => 'Order Created Successfully',
            'order' => $order,
            'payment' => $link
        ]);
    }

    private function sendCustomerEmail($order)
    {
        if ($order->customer && $order->customer->profile->email)
            dispatch(new OrderBillEmail($order));
    }

    public function delete($partner, $order, Request $request, PosOrderDeleter $deleter)
    {
        try {
            $partner = $request->partner;
            $modifier = $request->manager_resource;
            if($partner->isMigrated(Modules::POS))  return api_response($request, null, 403,["message" =>'অনুগ্রহ করে অ্যাপটি প্লে-স্টোর থেকে আপডেট করুন']);
            $deleter->setPartner($request->partner)->setOrder($order)->delete();
            /** USAGE LOG */
            (new Usage())->setUser($partner)->setType(Usage::Partner()::POS_ORDER_DELETE)->create($modifier);
            return api_response($request, true, 200);
        } catch (PosExpenseCanNotBeDeleted $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, $e->getCode(), ['message' => $e->getMessage()]);
        } catch (InvalidPosOrder $e) {
            return api_response($request, null, $e->getCode(), ['message' => $e->getMessage()]);
        }
    }

    /**
     * @param Request $request
     * @param QuickCreator $creator
     * @return JsonResponse
     */
    public function quickStore(Request $request, QuickCreator $creator)
    {
        $this->validate($request, [
            'amount' => 'required|numeric',
            /*'paid_amount' => 'required|numeric',
            'payment_method' => 'required|string|in:' . implode(',', config('pos.payment_method'))*/
        ]);
        $partner = $request->partner;
        if($partner->isMigrated(Modules::POS))  return api_response($request, null, 403,["message" =>'অনুগ্রহ করে অ্যাপটি প্লে-স্টোর থেকে আপডেট করুন']);
        $this->setModifier($request->manager_resource);
        $order = $creator->setPartner($partner)->setData($request->all())->create();
        $order = $order->calculate();
        /**
         * TURNED OFF POS ORDER CREATE SMS BY SERVER END, HANDLED BY CLIENT SIDE
         *
         * if ($partner->wallet >= 1) $this->sendCustomerSms($order);
         */
        $this->sendCustomerEmail($order);
        $order->payment_status = $order->getPaymentStatus();
        $order["client_pos_order_id"] = $request->has('client_pos_order_id') ? $request->client_pos_order_id : null;
        return api_response($request, null, 200, [
            'msg' => 'Order Created Successfully',
            'order' => $order
        ]);
    }

    /**
     * @param Request $request
     * @param Updater $updater
     * @param InvoiceService $invoiceService
     * @return JsonResponse
     * @throws NotAssociativeArray
     */
    public function update(Request $request, Updater $updater, InvoiceService $invoiceService)
    {
        $partner = $request->partner;
        $modifier = $request->manager_resource;
        if($partner->isMigrated(Modules::POS))  return api_response($request, null, 403,["message" =>'অনুগ্রহ করে অ্যাপটি প্লে-স্টোর থেকে আপডেট করুন']);
        $this->setModifier($request->manager_resource);
        /** @var PosOrder $order */
        $new           = 1;
        $order         = PosOrder::with('items')->find($request->order);
        $is_returned   = ($this->isReturned($order, $request, $new));
        $refund_nature = $is_returned ? Natures::RETURNED : Natures::EXCHANGED;
        $return_nature = $is_returned ? $this->getReturnType($request, $order) : null;
        /** @var RefundNature $refund */
        $refund = NatureFactory::getRefundNature($order, $request->all(), $refund_nature, $return_nature);
        $request->merge(['refund_nature' => $refund_nature]);
        $refund->setNew($new)->update();
        $invoiceService->setPosOrder($order)->generateInvoice()->saveInvoiceLink();
            $order->payment_status = $order->calculate()->getPaymentStatus();
        /** USAGE LOG */
        (new Usage())->setUser($partner)->setType(Usage::Partner()::POS_ORDER_UPDATE)->create($modifier);
            return api_response($request, null, 200, [
                'msg'   => 'Order Updated Successfully',
                'order' => $order
            ]);
    }

    /**
     * @param Request $request
     * @param StatusChanger $statusChanger
     * @return JsonResponse
     * @throws ExpenseTrackingServerError
     */
    public function updateStatus(Request $request, StatusChanger $statusChanger)
    {
        $partner = $request->partner;
        if($partner->isMigrated(Modules::POS))  return api_response($request, null, 403,["message" =>'অনুগ্রহ করে অ্যাপটি প্লে-স্টোর থেকে আপডেট করুন']);
        $this->setModifier($request->manager_resource);
        $order = PosOrder::with('items')->find($request->order);
        $statusChanger->setOrder($order)->setStatus($request->status)->setModifier($request->manager_resource)->changeStatus();
        if ($order->partner->is_webstore_sms_active && $order->partner->wallet >= 1 && $order->sales_channel == SalesChannels::WEBSTORE) {
            try {
                dispatch(new WebstoreOrderSms($order->partner, $order->id));
            } catch (Throwable $e) {
                app('sentry')->captureException($e);
            }
        }
        return api_response($request, null, 200, ['message' => 'Status Updated Successfully']);
    }

    /**
     * @param PosOrder $order
     * @param Request $request
     * @param bool $new
     * @return bool
     */
    private function isReturned(PosOrder $order, Request $request, $new = false)
    {
        if ($new) {
            $services = $order->items->pluck('id')->toArray();
        } else {
            $services = $order->items->pluck('service_id')->toArray();
        }
        $request_services = collect(json_decode($request->services, true))->pluck('id')->toArray();
        return $services === $request_services;
    }

    private function getReturnType(Request $request, PosOrder $order)
    {
        $request_services_quantity = collect(json_decode($request->services, true))->pluck('quantity')->toArray();
        $is_full_order_returned = (empty(array_filter($request_services_quantity)));
        $is_item_added = array_sum($request_services_quantity) > $order->items->sum('quantity');
        return $is_full_order_returned ? ReturnNatures::FULL_RETURN : ($is_item_added ? ReturnNatures::QUANTITY_INCREASE : ReturnNatures::PARTIAL_RETURN);
    }

    /**
     * SMS TO CUSTOMER ABOUT POS ORDER BILLS
     *
     * @param  Request  $request
     * @param  Updater  $updater
     * @return JsonResponse
     */
    public function sendSms(Request $request, Updater $updater)
    {
        $this->sendSmsCore($request, $updater);
        return api_response($request, null, 200, ['msg' => 'SMS Send Successfully']);
    }

    /**
     * @param Request $request
     * @param Updater $updater
     * @return JsonResponse
     */
    public function sendSmsV2(Request $request, Updater $updater)
    {
        $this->sendSmsCore($request, $updater);
        return http_response($request, null, 200, ['msg' => 'SMS Send Successfully']);
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
        $response = $this->sendEmailCore($request, $updater);
        return api_response($request, null, $response['code'], ['msg' => $response['msg']]);
    }

    /**
     * @param Request $request
     * @param Updater $updater
     * @return JsonResponse
     */
    public function sendEmailV2($order, Request $request)
    {
        $partner = $request->auth_user->getPartner();
        $partner = Partner::find($partner->id);
        if ($partner->isMigrated(Modules::POS)) {
            return $this->posOrderServerClient->post('api/v1/partners/' . $partner->id . '/orders/' . $order . '/send-email', null);
        }
        $response = $this->sendEmailCore($request);
        return http_response($request, null, $response['code'], ['msg' => $response['msg']]);
    }

    public function collectPayment(Request $request, PaymentCreator $payment_creator)
    {
        $this->setModifier($request->manager_resource);
        $this->validate($request, [
            'paid_amount' => 'required|numeric',
            'payment_method' => 'required|string|in:' . implode(',', config('pos.payment_method')),
            'emi_month' => 'required_if:payment_method,emi'
        ]);
        /** @var PosOrder $order */
        $order = PosOrder::find($request->order);
        $payment_data = [
            'pos_order_id' => $order->id,
            'amount' => $request->paid_amount,
            'method' => $request->payment_method
        ];
        if ($request->has('emi_month')) {
            $payment_data['emi_month'] = $request->emi_month;
        }

        $payment_creator->credit($payment_data);
        $order = $order->calculate();
        $order->payment_status = $order->getPaymentStatus();
        $this->updateIncome($order, $request->paid_amount, $request->emi_month);
        /**
         * USAGE LOG
         */
        (new Usage())->setUser($request->partner)->setType(Usage::Partner()::POS_DUE_COLLECTION)->create($request->manager_resource);
        return api_response($request, null, 200, [
            'msg' => 'Payment Collect Successfully',
            'order' => $order
        ]);
    }

    /**
     * @param PosOrder $order
     * @param          $paid_amount
     * @param          $emi_month
     * @throws ExpenseTrackingServerError
     */
    private function updateIncome(PosOrder $order, $paid_amount, $emi_month)
    {
        /** @var AutomaticEntryRepository $entry */
        $entry = app(AutomaticEntryRepository::class);
        $amount = (double)$order->getNetBill();
        $entry->setPartner($order->partner)->setAmount($amount)->setAmountCleared($paid_amount)->setFor(EntryType::INCOME)->setSourceType(class_basename($order))->setSourceId($order->id)->setCreatedAt($order->created_at)->setEmiMonth($emi_month)->updateFromSrc();
    }

    /**
     * @param Request $request
     * @param $partner
     * @param PosOrder $order
     * @param InvoiceService $invoiceService
     * @return JsonResponse
     * @throws AccessRestrictedExceptionForPackage
     * @throws NotAssociativeArray
     */
    public function downloadInvoice(Request $request, $partner, PosOrder $order, InvoiceService $invoiceService)
    {
        AccessManager::checkAccess(AccessManager::Rules()->POS->INVOICE->DOWNLOAD, $request->partner->subscription->getAccessRules());
        $invoiceService = $invoiceService->setPosOrder($order);
        $invoiceLink = $invoiceService->isAlreadyGenerated()->getInvoiceLink();
        if (!$invoiceLink)
            $invoiceLink = $invoiceService->generateInvoice()->saveInvoiceLink()->getInvoiceLink();
        return api_response($request, null, 200, [
            'message' => 'Successfully Download receipt',
            'link' => $invoiceLink
        ]);
    }

    /**
     * @throws NotAssociativeArray
     */
    public function downloadInvoiceFromWebStore(Request $request, $partner, PosOrder $order, InvoiceService $invoiceService)
    {
        $invoiceService = $invoiceService->setPosOrder($order);
        $invoiceLink = $invoiceService->isAlreadyGenerated()->getInvoiceLink();
        if (!$invoiceLink)
            $invoiceLink = $invoiceService->generateInvoice()->saveInvoiceLink()->getInvoiceLink();
        return api_response($request, null, 200, [
            'message' => 'Successfully Download receipt',
            'link' => $invoiceLink
        ]);
    }

    /**
     * @param Request $request
     * @param $partner
     * @param PosOrder $order
     * @return JsonResponse|string
     */
    public function storeNote(Request $request, $partner, PosOrder $order)
    {
        $this->validate($request, ['note' => 'required']);
        $this->setModifier($request->manager_resource);
        $order->note = $request->note;
        $order->update();
        return api_response($request, null, 200, [
            'msg' => 'Note created successfully',
            'order' => $order
        ]);
    }


    /**
     * @param PosOrder $order
     */
    private function sendOrderPlacePushNotificationToPartner(PosOrder $order)
    {
        try {
            (new WebstorePushNotificationHandler())->setOrder($order)->handle();
        } catch (Throwable $e) {
            logError($e);
        }
    }

    /**
     * @param Request $request
     * @param Updater $updater
     * @return JsonResponse
     */
    public function tagCustomer(Request $request, Updater $updater)
    {
        $this->validate($request, [
            'customer_id' => 'required'
        ]);
        $this->setModifier($request->manager_resource);
        /** @var PosOrder $order */
        $order = PosOrder::find($request->order);
        if (!$order)
            return api_response($request, null, 404, ['msg' => 'Order not found']);
        if ($order->partner_id != $request->partner->id)
            return api_response($request, null, 403, ['msg' => 'Order and Partner mismatch']);
        $requested_customer = PosCustomer::find($request->customer_id);
        if (!$requested_customer)
            return api_response($request, null, 401, ['msg' => 'Customer not found']);
        $updater->setOrder($order)->setData(['customer_id' => $requested_customer->id])->update();
        /** @var AutomaticEntryRepository $entry */
        $entry = app(AutomaticEntryRepository::class);
        $entry->setPartner($order->partner)->setFor(EntryType::INCOME)->setSourceType(class_basename($order))->setSourceId($order->id)->setParty($requested_customer->profile)->updatePartyFromSource();
        return api_response($request, null, 200, ['msg' => 'Customer tagged Successfully']);
    }

    private function sendSmsCore(Request $request, Updater $updater)
    {
        $partner = resolvePartnerFromAuthMiddleware($request);
        $this->setModifier(resolveManagerResourceFromAuthMiddleware($request));
        $this->dispatch(new OrderBillSms($partner, $request->order));
    }

    private function sendEmailCore(Request $request)
    {
        $this->setModifier(resolveManagerResourceFromAuthMiddleware($request));
        /** @var PosOrder $order */
        $order = PosOrder::with('items')->find($request->order)->calculate();

        if (!$order)
            return (['code' => 404, 'msg' => 'Order not found']);
        if (!$order->customer)
            return (['code' => 404, 'msg' => 'Customer not found']);
        if (!$order->customer->profile->email)
            return (['code' => 404, 'msg' => 'Customer email not found']);
        dispatch(new OrderBillEmail($order));
        return (['code' => 200, 'msg' => 'Email Send Successfully']);
    }

    /**
     * @throws UnauthorizedRequestFromExpenseTrackerException
     */
    public function createPayment(Request $request, PosOrderPaymentRepository $posOrderPaymentRepository)
    {
        $this->validate($request, [
            'amount' => 'required',
            'pos_order_id' => 'required',
            'payment_method' => 'sometimes|string|in:' . implode(',', config('pos.payment_method')),
            'api_key' => 'required',
        ]);
//        if($request->api_key != 'sheba_xyz_acc_key')
//            throw new UnauthorizedRequestFromExpenseTrackerException("Unauthorized Request");
        //from expense server payment details always advance cash
        $method_details = ['payment_method_en' => 'Cash', 'payment_method_bn' => ' নগদ গ্রহন', 'payment_method_icon' => config('s3.url') . 'pos/payment/cash_v2.png'];
        $posOrderPaymentRepository->setMethodDetails($method_details)->createPosOrderPayment($request->amount, $request->pos_order_id,$request->payment_method);
        return api_response($request, true, 200, ['message' => 'Pos Order Payment created successfully']);
    }

    /**
     * @throws UnauthorizedRequestFromExpenseTrackerException
     */
    public function removePayment(Request $request, $pos_order_id, PosOrderPaymentRepository $posOrderPaymentRepository)
    {
        $this->validate($request, [
            'api_key' => 'required',
        ]);
//        if($request->api_key != 'sheba_xyz_acc_key')
//            throw new UnauthorizedRequestFromExpenseTrackerException("Unauthorized Request");
        $result = $posOrderPaymentRepository->removePosOrderPayment($pos_order_id, $request->amount);
        $message = null;
        if($result) $message = 'Pos Order Payment remove successfully';
        else $message = 'There is no Pos Order Payment';
        return api_response($request, true, 200, ['message' => $message]);
    }

}
