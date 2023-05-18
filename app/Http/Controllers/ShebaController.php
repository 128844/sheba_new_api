<?php namespace App\Http\Controllers;

use App\Http\Presenters\PresentableDTOPresenter;
use App\Http\Requests\AppVersionRequest;
use App\Jobs\SendFaqEmail;
use App\Models\HyperLocal;
use App\Models\Job;
use App\Models\OfferShowcase;
use App\Models\Partner;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\Resource;
use Sheba\Auth\JWTAuth;
use Sheba\Dal\EmiBank\Repository\EmiBankContract;
use Sheba\Dal\PaymentGateway\Contract as PaymentGatewayRepository;
use Sheba\Dal\Service\Service;
use App\Models\Slider;
use App\Models\SliderPortal;
use App\Repositories\CustomerRepository;
use App\Repositories\ReviewRepository;
use App\Repositories\ServiceRepository;
use Cache;
use DB;
use Exception;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sheba\AppVersion\AppVersionManager;
use Sheba\Dal\Attendance\Contract as AttendanceRepoInterface;
use Sheba\Dal\Category\Category;
use Sheba\Dal\MetaTag\MetaTagRepositoryInterface;
use Sheba\Dal\RedirectUrl\RedirectUrl;
use Sheba\Dal\UniversalSlug\Model as SluggableType;
use Sheba\EMI\Banks;
use Sheba\EMI\Calculator;
use Sheba\EMI\CalculatorForManager;
use Sheba\NID\Validations\NidValidation;
use Sheba\OAuth2\AuthUser;
use Sheba\Payment\AvailableMethods;
use Sheba\Payment\Presenter\PaymentMethodDetails;
use Sheba\Payment\Statuses;
use Sheba\Repositories\PaymentLinkRepository;
use Sheba\Transactions\Wallet\HasWalletTransaction;
use Throwable;
use Validator;

class ShebaController extends Controller
{
    use DispatchesJobs;

    private $serviceRepository;
    private $reviewRepository;
    private $paymentLinkRepo;

    public function __construct(ServiceRepository $service_repo, ReviewRepository $review_repo, PaymentLinkRepository $paymentLinkRepository)
    {
        $this->serviceRepository = $service_repo;
        $this->reviewRepository = $review_repo;
        $this->paymentLinkRepo = $paymentLinkRepository;
    }

    public function getInfo()
    {
        $job_count = Job::all()->count() + 16000;
        $service_count = Service::where('publication_status', 1)->get()->count();
        $resource_count = Resource::where('is_verified', 1)->get()->count();
        return response()->json([
            'service'  => $service_count, 'job' => $job_count,
            'resource' => $resource_count,
            'msg'      => 'successful', 'code' => 200
        ]);
    }

    public function sendFaq(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string',
            'email'   => 'required|email',
            'subject' => 'required|string',
            'message' => 'required|string'
        ]);
        if ($validator->fails()) {
            return api_response($request, null, 500, ['message' => $validator->errors()->all()[0]]);
        }
        $this->dispatch(new SendFaqEmail($request->all()));
        return api_response($request, null, 200);
    }

    public function getImages(Request $request)
    {
        if ($request->has('is_business') && (int)$request->is_business) {
            $portal_name = 'manager-app';
            $screen = 'eshop';

            if (!$request->has('location')) $location = 4;
            else $location = $request->location;
        } else if ($request->has('is_ddn') && (int)$request->is_ddn) {
            $portal_name = 'bondhu-app';
            $screen = 'eshop';

            if (!$request->has('location')) $location = 4;
            else $location = $request->location;
        } else {
            if ($request->has('location')) {
                $location = $request->location;
            } else {
                if ($request->has('lat') && $request->has('lng')) {
                    $hyperLocation = HyperLocal::insidePolygon((double)$request->lat, (double)$request->lng)->with('location')->first();
                    if (!is_null($hyperLocation)) $location = $hyperLocation->location->id;
                }
            }

            $portal_name = $request->portal;
            $screen = $request->screen;
        }

        $slider = $this->getSliderWithSlides($location, $portal_name, $screen);

        if (!is_null($slider)) return api_response($request, $slider->slides, 200, ['images' => $slider->slides]);
        else return api_response($request, null, 404);

        /**
         * Previous Codes, Left Written Until QA
         *
         * $images = Slider::select('id', 'image_link', 'small_image_link', 'target_link', 'target_type', 'target_id');
         * if ($request->has('is_business') && (int)$request->is_business) {
         * $images = $images->showBusiness()->map(function ($image) {
         * $image['target_type'] = $image['target_type'] ? explode('\\', $image['target_type'])[2] : null;
         * return $image;
         * });
         * } else {
         * $images = $images->show();
         * }
         * return count($images) > 0 ? api_response($request, $images, 200, ['images' => $images]) : api_response($request, null, 404);*/
    }

    /**
     * @param $location
     * @param $portal_name
     * @param $screen
     * @return Slider
     */
    private function getSliderWithSlides($location, $portal_name, $screen)
    {
        $sliderPortal = SliderPortal::with('slider')->whereHas('slider', function ($query) use ($location) {
            $query->where('is_published', 1);
        })->where('portal_name', $portal_name)->where('screen', $screen)->first();

        $slider = $sliderPortal->slider()->with([
            'slides' => function ($q) use ($location) {
                $q->where('location_id', $location)->orderBy('order');
            }
        ])->first();

        return $slider;
    }

    public function getSimilarOffer($offer)
    {
        $offer = OfferShowcase::select('id', 'thumb', 'title', 'banner', 'short_description', 'detail_description', 'target_link')
            ->where([
                ['id', '<>', $offer],
                ['is_active', 1]
            ])->get();
        return count($offer) >= 3 ? response()->json(['offer' => $offer, 'code' => 200]) : response()->json(['code' => 404]);
    }

    public function getLeadRewardAmount()
    {
        return response()->json(['code' => 200, 'amount' => constants('AFFILIATION_REWARD_MONEY')]);
    }

    public function getVersions(AppVersionRequest $request, AppVersionManager $app_version_manager)
    {
        if ($request->wantsSingleApp()) {
            $data = $app_version_manager->getVersionForApp($request->app, (int)$request->version)->toArray();
            return api_response($request, $data, 200, ['data' => $data]);
        }

        $apps = $app_version_manager->getAllAppVersions();
        return api_response($request, $apps, 200, ['apps' => $apps]);
    }

    public function sendCarRentalInfo(Request $request)
    {
        $ids = array_map('intval', explode(',', env('RENT_CAR_IDS')));
        $categories = Category::whereIn('id', $ids)->select('id', 'name', 'parent_id')->get();
        return api_response($request, $categories, 200, ['info' => $categories]);
    }

    public function sendButcherInfo(Request $request)
    {
        $butcher_service = Service::find((int)env('BUTCHER_SERVICE_ID'));
        if (!$butcher_service) return api_response($request, null, 404);
        $butcher_info = [
            'id'           => $butcher_service->id,
            'category_id'  => $butcher_service->category_id,
            'name'         => $butcher_service->name,
            'unit'         => $butcher_service->unit,
            'min_quantity' => (double)$butcher_service->min_quantity,
            'price_info'   => json_decode($butcher_service->variables),
            'date'         => "2018-08-21"
        ];
        return api_response($request, $butcher_info, 200, ['info' => $butcher_info]);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param $transaction_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkTransactionStatus(Request $request, $transaction_id): JsonResponse
    {
        /** @var Payment $payment */
        $payment = Payment::where('transaction_id', $transaction_id)->first();
        if (!$payment) return api_response($request, null, 404, ['message' => 'No Payment found']);
        $external_payment = $payment->externalPayments;

        if (!$payment->isComplete() && !$payment->isPassed()) {
            if ($error = $payment->getErrorMessage()) $message = 'Your payment has been failed due to ' . $error;
            else $message = 'Payment Failed.';

            $fail_url = null;
            if ($external_payment) $fail_url = $external_payment->fail_url;

            return api_response($request, null, 404, ['message' => $message, 'external_payment_redirection_url' => $fail_url]);
        }
        $info = $this->getPaymentInfo($payment, $external_payment);

        $message = $payment->isPassed() ?
            'Your payment has been received but there was a system error. It will take some time to update your transaction. Call 16516 for support.' :
            'Successful';

        return api_response($request, null, 200, ['info' => $info, 'message' => $message]);
    }

    private function getPaymentInfo(Payment $payment, $external_payment = null)
    {

        /** @var Payable $payable */
        $payable = $payment->payable;
        $info = [
            'amount'                           => $payable->amount,
            'method'                           => $payment->paymentDetails->last()->readable_method,
            'description'                      => $payable->description,
            'created_at'                       => $payment->created_at->format('jS M, Y, h:i A'),
            'invoice_link'                     => $payment->invoice_link,
            'transaction_id'                   => $payment->transaction_id,
            'external_payment_redirection_url' => $external_payment ? $external_payment->success_url : null,
            'store'                            => $payment->gateway_account_name
        ];
        if ($payable->isPaymentLink()) $this->mergePaymentLinkInfo($info, $payable);

        return $info;
    }

    private function mergePaymentLinkInfo(&$info, Payable $payable)
    {
        $payment_link = $this->paymentLinkRepo->find($payable->type_id);
        $receiver = $payment_link->getPaymentReceiver();
        $payer = $payable->user->profile;
        $info = array_merge($info, $this->getInfoForPaymentLink($payer, $receiver));
    }

    private function getInfoForPaymentLink(Profile $payer, HasWalletTransaction $receiver)
    {
        return [
            'payment_receiver' => [
                'receiver' => $receiver->id,
                'name'     => $receiver->name,
                'image'    => $receiver->logo,
                'mobile'   => $receiver->getMobile(),
                'address'  => $receiver->address
            ],
            'payer'            => [
                'id'     => $payer->id,
                'name'   => $payer->name,
                'mobile' => $payer->mobile
            ]
        ];
    }

    /**
     * @param Request $request
     * @param PaymentGatewayRepository $paymentGateWayRepository
     * @return JsonResponse
     * @throws Exception
     */
    public function getPayments(Request $request, PaymentGatewayRepository $paymentGateWayRepository)
    {
        $version_code = (int)$request->header('Version-Code');
        $platform_name = $request->header('Platform-Name');
        $user_type = $request->type;
        $payable_type = $request->payable_type;
        /** @var Partner $partner */
        $partner = null;
        if (!$user_type) $user_type = getUserTypeFromRequestHeader($request);
        if (!$user_type) $user_type = "customer";
        if ($user_type === "partner") {
            try {
                $token = JWTAuth::getToken();
                if ($token) {
                    $authUser = AuthUser::createFromToken($token);
                    $partner = $authUser->getPartner();
                }
            } catch (\Throwable $e) {

            }
        }
        if (!empty($partner) && $partner->isMxPartner()) {
            $user_type = 'customer';
            $request->merge(['payable_type_id'=>$partner->id]);
        }
        $serviceType = 'App\\Models\\' . ucfirst($user_type);
        $dbGateways = $paymentGateWayRepository->builder()
            ->where('service_type', $serviceType)
            ->whereNull('payment_method')
            ->where('status', 'Published')
            ->get();

        $payments = array_map(function (PaymentMethodDetails $details) use ($dbGateways, $user_type, $payable_type) {
            return (new PresentableDTOPresenter($details, $dbGateways))->mergeWithDbGateways($user_type, $payable_type);
        }, AvailableMethods::getDetails($request->payable_type, $request->payable_type_id, $version_code, $platform_name, $user_type));

        if ($user_type == 'partner') {
            $payments = array_filter($payments, function ($arr) {
                return $arr !== null;
            });
            $payments = array_values(collect($payments)->sortBy('order')->toArray());
        }

        return api_response($request, $payments, 200, [
            'payments'         => $payments,
            'discount_message' => 'Pay online and stay relaxed!!!'
        ]);
    }

    public function getEmiInfo(Request $request, Calculator $emi_calculator)
    {
        $amount = $request->amount;

        if (!$amount) {
            return api_response($request, null, 400, ['message' => 'Amount missing']);
        }

        if ($amount < config('emi.minimum_emi_amount')) {
            return api_response($request, null, 400, ['message' => 'Amount is less than minimum emi amount']);
        }

        $emi_data = [
            "emi"   => $emi_calculator->getCharges($amount),
            "banks" => (new Banks())->setAmount($amount)->get()
        ];

        return api_response($request, null, 200, ['price' => $amount, 'info' => $emi_data]);
    }

    public function getEmiInfoV3(Request $request, Calculator $emi_calculator)
    {
        $amount = $request->amount;
        if (!$amount) {
            $amount = 5000;
        }

        if ($amount < config('emi.minimum_emi_amount')) {
            return api_response($request, null, 400, ['message' => 'Amount is less than minimum emi amount']);
        }
        $emi_data = [
            "emi"            => $emi_calculator->getCharges($amount),
            "banks"          => (new Banks())->setAmount($amount)->get(),
            "minimum_amount" => number_format(config('sheba.min_order_amount_for_emi')),
            "static_info"    => [
                "how_emi_works"        => [
                    "EMI (Equated Monthly Installment) is one of the payment methods of online purchasing, only for the customers using any of the accepted Credit Cards on Sheba.xyz.* It allows customers to pay for their ordered services  in easy equal monthly installments.*",
                    "Sheba.xyz has introduced a convenient option of choosing up to 12 months EMI facility for customers who use Credit Cards for buying services worth BDT 5,000 or more. The duration and extent of the EMI options available will be visible on the payment page after order placement. EMI plans are also viewable on the checkout page in the EMI Banner below the bill section.",
                    "Customers wanting to avail EMI facility must have a Credit Card from any one of the banks in the list shown in the payment page.",
                    "EMI facilities available for all services worth BDT 5,000 or more.",
                    "EMI charges may vary on promotional offers.",
                    "Sheba.xyz  may charge additional convenience fee if the customer extends the period of EMI offered."
                ],
                "terms_and_conditions" => [
                    "As soon as you complete your purchase order on Sheba.xyz, you will see the full amount charged on your credit card.",
                    "You must Sign and Complete the EMI form and submit it at Sheba.xyz within 3 working days.",
                    "Once Sheba.xyz receives this signed document from the customer, then it shall be submitted to the concerned bank to commence the EMI process.",
                    "The EMI processing will be handled by the bank itself *. After 5-7 working days, your bank will convert this into EMI.",
                    "From your next billing cycle, you will be charged the EMI amount and your credit limit will be reduced by the outstanding amount.",
                    "If you do not receive an updated monthly bank statement reflecting your EMI transactions for the following month, feel free to contact us at 16516  for further assistance.",
                    "For example, if you have made a 3-month EMI purchase of BDT 30,000 and your credit limit is BDT 1, 00,000 then your bank will block your credit limit by BDT 30,000 and thus your available credit limit after the purchase will only be BDT 70,000. As and when you pay your EMI every month, your credit limit will be released accordingly.",
                    "EMI facilities with the aforesaid Banks are regulated as per their terms and conditions and these terms may vary from one bank to another.",
                    "For any query or concern please contact your issuing bank, if your purchase has not been converted to EMI by 7 working days of your transaction date."
                ]
            ]
        ];

        return api_response($request, null, 200, ['price' => number_format($amount), 'info' => $emi_data]);
    }

    public function emiInfoForManager(Request $request, CalculatorForManager $emi_calculator)
    {
        try {
            $this->validate($request, ['amount' => 'required|numeric|min:' . config('emi.manager.minimum_emi_amount')]);
            $amount = $request->amount;
            $icons_folder = getEmiBankIconsFolder(true);
            $emi_data = [
                "emi"            => $emi_calculator->getCharges($amount),
                "banks"          => (new Banks())->setAmount($amount)->get(),
                "minimum_amount" => number_format(config('sheba.min_order_amount_for_emi')),
                "static_info"    => [
                    "how_emi_works"        => [
                        "EMI (Equated Monthly Installment) is one of the payment methods of online purchasing, only for the customers using any of the accepted Credit Cards on Sheba.xyz.* It allows customers to pay for their ordered services  in easy equal monthly installments.*",
                        "Sheba.xyz has introduced a convenient option of choosing up to 12 months EMI facility for customers who use Credit Cards for buying services worth BDT 5,000 or more. The duration and extent of the EMI options available will be visible on the payment page after order placement. EMI plans are also viewable on the checkout page in the EMI Banner below the bill section.",
                        "Customers wanting to avail EMI facility must have a Credit Card from any one of the banks in the list shown in the payment page.",
                        "EMI facilities available for all services worth BDT 5,000 or more.",
                        "EMI charges may vary on promotional offers.",
                        "Sheba.xyz  may charge additional convenience fee if the customer extends the period of EMI offered."
                    ],
                    "terms_and_conditions" => [
                        "As soon as you complete your purchase order on Sheba.xyz, you will see the full amount charged on your credit card.",
                        "You must Sign and Complete the EMI form and submit it at Sheba.xyz within 3 working days.",
                        "Once Sheba.xyz receives this signed document from the customer, then it shall be submitted to the concerned bank to commence the EMI process.",
                        "The EMI processing will be handled by the bank itself *. After 5-7 working days, your bank will convert this into EMI.",
                        "From your next billing cycle, you will be charged the EMI amount and your credit limit will be reduced by the outstanding amount.",
                        "If you do not receive an updated monthly bank statement reflecting your EMI transactions for the following month, feel free to contact us at 16516  for further assistance.",
                        "For example, if you have made a 3-month EMI purchase of BDT 30,000 and your credit limit is BDT 1, 00,000 then your bank will block your credit limit by BDT 30,000 and thus your available credit limit after the purchase will only be BDT 70,000. As and when you pay your EMI every month, your credit limit will be released accordingly.",
                        "EMI facilities with the aforesaid Banks are regulated as per their terms and conditions and these terms may vary from one bank to another.",
                        "For any query or concern please contact your issuing bank, if your purchase has not been converted to EMI by 7 working days of your transaction date."
                    ]
                ]
            ];

            return api_response($request, null, 200, ['price' => $amount, 'info' => $emi_data]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function nidValidate(Request $request)
    {
        try {
            $this->validate($request, NidValidation::$RULES);
            $nidValidation = new NidValidation();
            if ($request->has('manager_resource')) {
                $exists = Profile::query()
                    ->where('nid_no', $request->nid)
                    ->whereNotIn('id', [$request->manager_resource->profile->id])
                    ->first();
                if (!empty($exists)) return api_response($request, null, 400, ['message' => 'Nid Number is used by another user']);
                if ($request->manager_resource->profile->nid_verified == 1) return api_response($request, null, 400, ['message' => 'NID is already verified']);
                $nidValidation->setProfile($request->manager_resource->profile);
            }
            $check = $nidValidation->validate($request->nid, $request->full_name, $request->dob);
            if ($check['status'] === 1) {
                $nidValidation->complete();
                return api_response($request, true, 200, ['message' => 'NID verification completed']);
            }
            return api_response($request, null, 400, ['message' => isset($check['message']) ? $check['message'] : 'NID is not verified']);
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

    public function getSluggableType(Request $request, $slug, MetaTagRepositoryInterface $meta_tag_repository)
    {
        $type = SluggableType::where('slug', $slug)->select('sluggable_type', 'sluggable_id')->first();
        if (!$type) return api_response($request, null, 404);
        if ($type->sluggable_type == 'service') $model = 'service';
        else $model = 'category';
        $meta_tag = $meta_tag_repository->builder()->select('meta_tag', 'og_tag')->where('taggable_type', 'like', '%' . $model)->where('taggable_id', $type->sluggable_id)->first();
        $sluggable_type = [
            'type'     => $type->sluggable_type,
            'id'       => $type->sluggable_id,
            'meta_tag' => $meta_tag && $meta_tag->meta_tag ? json_decode($meta_tag->meta_tag) : null,
            'og_tag'   => $meta_tag && $meta_tag->og_tag ? json_decode($meta_tag->og_tag) : null,
        ];
        return api_response($request, true, 200, ['sluggable_type' => $sluggable_type]);
    }

    public function redirectUrl(Request $request)
    {
        $this->validate($request, ['url' => 'required']);
        $new_url = RedirectUrl::where('old_url', '=', $request->url)->first();
        if (!$new_url) return api_response($request, true, 404, ['message' => 'Not Found']);
        return api_response($request, true, 200, ['new_url' => $new_url->new_url]);
    }

    public function registerCustomer(Request $request, CustomerRepository $cr)
    {
        $info = ['mobile' => $request->mobile];
        $cr->registerMobile($info);
    }

    public function getHourLogs(Request $request, AttendanceRepoInterface $attendance_repo)
    {
        $this->validate($request, ['start_date' => 'required|date_format:Y-m-d', 'end_date' => 'required|date_format:Y-m-d']);
        $ids = json_decode($request->id);
        $attendances = $attendance_repo->builder()
            ->whereIn('business_member_id', $ids)->where([['date', ">=", $request->start_date], ['date', '<=', $request->end_date]])
            ->select('id', 'business_member_id', 'date', 'checkin_time', 'checkout_time', 'staying_time_in_minutes')
            ->get();
        return api_response($request, null, 200, ['data' => $attendances->groupBy('business_member_id')]);
    }

    public function getEmiBankList(Request $request, EmiBankContract $emiBankContract)
    {
        $bank_lists = $emiBankContract->builder()->get();
        $updated_bank_lists = [];
        for ($i = 0; $i < count($bank_lists); $i++) {
            if (!in_array($bank_lists[$i]->name, config('excluded_bank.excluded_bank'))) {
                $updated_bank_lists[] = $bank_lists[$i];
            }
        }
        return api_response($request, null, 200, ['data' => $updated_bank_lists]);
    }

    public function paymentInitiatedInfo(Request $request, $transaction_id)
    {
        /** @var Payment $payment */
        $payment = Payment::where('transaction_id', $transaction_id)->first();
        if (!$payment) return api_response($request, null, 404, ['message' => 'No Payment found']);
        if ($payment->status != Statuses::INITIATED) return api_response($request, null, 400, ['message' => 'Payment already processed please contact with the the seller or call sheba platform limited']);
        $info = $this->getPaymentInfo($payment);
        $info['gateway_transaction_id'] = $payment->gateway_transaction_id;
        $info['gateway_account_name'] = $payment->gateway_account_name;
        $info['payment_purpose'] = $payment->payable->description;
        if (!isset($info['payer'])) {
            /** @var Payable $payer */
            $payer = $payment->payable;
            $info = array_merge($info, ['payer' => ['name' => $payer->getName(), 'id' => $payer->user->id, 'mobile' => $payer->getMobile()]]);
        }
        return api_response($request, $info, 200, ['data' => $info]);
    }
}
