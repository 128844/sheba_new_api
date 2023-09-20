<?php namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FacebookAccountKit;
use App\Models\BusinessJoinRequest;
use App\Models\Partner;
use App\Models\PartnerAffiliation;
use App\Models\PartnerBasicInformation;
use App\Models\PartnerReferral;
use App\Models\PartnerWalletSetting;
use App\Models\Profile;
use App\Models\Resource;
use App\Repositories\PartnerRepository;
use App\Repositories\ProfileRepository;
use App\Sheba\UserMigration\UserMigrationService;
use Exception;
use Sheba\Gender\Gender;
use Sheba\OAuth2\AccountServerAuthenticationError;
use Sheba\OAuth2\AccountServerNotWorking;
use Sheba\ShebaPay\Requests\ShebaPayRegistrationRequest;
use Sheba\Sms\BusinessType;
use Sheba\Sms\FeatureType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Sheba\ExpenseTracker\Exceptions\ExpenseTrackingServerError;
use Sheba\ExpenseTracker\Repository\EntryRepository;
use Sheba\ModificationFields;
use Sheba\Referral\Referrals;
use Sheba\Repositories\Interfaces\Partner\PartnerRepositoryInterface;
use Sheba\Reward\ActionRewardDispatcher;
use Sheba\Sms\Sms;
use Sheba\Subscription\Partner\PartnerSubscription;
use Sheba\Voucher\Creator\Referral;
use Throwable;
use Sheba\Dal\UserMigration\Model as UserMigration;

class PartnerRegistrationController extends Controller
{
    use ModificationFields;

    /** @var FacebookAccountKit $fbKit */
    private $fbKit;
    /** @var ProfileRepository $profileRepository */
    private $profileRepository;
    /** @var EntryRepository $entryRepo */
    private $entryRepo;
    /** @var PartnerRepositoryInterface $partnerRepo */
    private $partnerRepo;
    /**
     * @var Referrals
     */
    private $referrals;
    /** @var PartnerReferral */
    private $ref;

    public function __construct(EntryRepository $entry_repo, PartnerRepositoryInterface $partner_repo, Referrals $referrals)
    {
        $this->fbKit = new FacebookAccountKit();
        $this->profileRepository = new ProfileRepository();
        $this->entryRepo = $entry_repo;
        $this->partnerRepo = $partner_repo;
        $this->referrals = $referrals;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getWelcomeMessage(Request $request)
    {
        $data = [
            'image' => config('s3.url') . config('sheba.welcome_message_image'),
            'message' => 'বোনাস ব্যবহার করে sManager অ্যাপ  সাবস্ক্রাইব করুন, পুরো এক মাসের জন্য  সম্পুর্ন ফ্রি'
        ];
        return api_response($request, null, 200, ['info' => $data]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ExpenseTrackingServerError
     * @throws \Sheba\OAuth2\AccountServerAuthenticationError
     * @throws \Sheba\OAuth2\AccountServerNotWorking
     */
    public function register(Request $request): JsonResponse
    {
        ini_set('max_execution_time', 220);

        $this->validate($request, [
            'code' => "required|string",
            'company_name' => 'required|string',
            'from' => 'string|in:' . implode(',', constants('FROM')),
            'package_id' => 'exists:partner_subscription_packages,id',
            'billing_type' => 'in:monthly,yearly'
        ]);
        $code_data = $this->fbKit->authenticateKit($request->code);
        if (!$code_data)
            return api_response($request, null, 401, ['message' => 'AccountKit authorization failed']);
        $mobile = formatMobile($code_data['mobile']);
        $request->merge(['phone' => $mobile, 'number' => $mobile]);
        list($profile, $resource) = $this->getProfileResource($mobile, $request);
        if ($resource->partnerResources->count() > 0)
            return api_response($request, null, 403, ['message' => 'You already have a company!']);
        list($partner, $info) = $this->makePartnerGetInfo($request, $profile, $resource);
        $business_join_reqs = BusinessJoinRequest::where('mobile', $mobile)->first();
        if ($business_join_reqs) {
            $partner->businesses()->sync(['business_id' => $business_join_reqs->business_id]);
            $business_join_reqs->update(['status' => 'successful']);
        }
        return api_response($request, null, 200, ['info' => $info]);
    }

    /**
     * @throws ExpenseTrackingServerError
     * @throws AccountServerAuthenticationError
     * @throws AccountServerNotWorking
     */
    public function registerShebaPay(ShebaPayRegistrationRequest $request): JsonResponse
    {
        ini_set('max_execution_time', 220);
        $mobile = $request->get('mobile');
        list($profile, $resource) = $this->getProfileResource($mobile, $request);
        if ($resource->partnerResources->count() > 0)
            return api_response($request, null, 403, ['message' => 'You already have a company!']);
        list($partner, $info) = $this->makePartnerGetInfo($request, $profile, $resource);
        $token = $this->profileRepository->getJwtToken($partner, 'partner');
        return api_response($request, null, 200, ['data' => array_merge($info, ['token' => $token])]);
    }

    /**
     * @throws ExpenseTrackingServerError
     * @throws AccountServerAuthenticationError
     * @throws AccountServerNotWorking
     * @throws Exception
     */
    private function makePartnerGetInfo(Request $request, $profile, $resource, $from = 'resource'): array
    {
        $data = $this->makePartnerCreateData($request);
        $partner = $this->createPartner($resource, $data);
        if (!$partner) throw new Exception("Unable to create Partner", 400);
        (new PartnerSubscription())->setRequestedPackage()->setPartner($partner)->createBasicSubscriptionRequest($resource)->updateSubscription();
        $info = $this->profileRepository->getProfileInfo($from, Profile::find($profile->id));
        return [$partner, $info];
    }

    private function getProfileResource($mobile, Request $request): array
    {
        if ($profile = $this->profileRepository->ifExist($mobile, 'mobile')) {
            $resource = $profile->resource;
            if (!$resource) {
                $resource = $this->profileRepository->registerAvatarByKit('resource', $profile);
            }
        } else {
            $profile = $this->profileRepository->registerMobile(array_merge($request->all(), ['mobile' => $mobile]));
            $resource = $this->profileRepository->registerAvatarByKit('resource', $profile);
        }
        return [$profile, $resource];
    }

    /**
     * @param Request $request
     * @return array
     */
    private function makePartnerCreateData(Request $request): array
    {
        $data = ['name' => $request->company_name];
        if ($request->has('from')) {
            if ($request->from == 'manager-app' || $request->from == 'affiliation-app')
                $data['registration_channel'] = constants('PARTNER_ACQUISITION_CHANNEL')['App']; elseif ($request->from == 'manager-web')
                $data['registration_channel'] = constants('PARTNER_ACQUISITION_CHANNEL')['Web'];
        } else {
            $data['registration_channel'] = $request->has('registration_channel') ? $request->get('registration_channel') : constants('PARTNER_ACQUISITION_CHANNEL')['App'];
        }
        $data['billing_type'] = $request->has('billing_type') ? $request->billing_type : 'monthly';
        $data['package_id'] = $request->has('package_id') ? $request->package_id : config('sheba.partner_lite_packages_id');
        $data['billing_start_date'] = Carbon::today();
        $data['last_billed_date'] = Carbon::today();
        $data['last_billed_amount'] = 0.00;
        if ($request->has('affiliate_id')) {
            $data['affiliate_id'] = $request->affiliate_id;
        }
        if ($request->has('address')) {
            $data['address'] = $request->address;
        }
        if ($request->has('number'))
            $data['mobile'] = formatMobile($request->number);
        if ($request->has('geo')) {
            $geo = json_decode($request->geo);
            $geo->radius = 5;
            $data['geo_informations'] = json_encode($geo);
        }
        if ($request->has('refer_code'))
            $data['refer_code'] = $request->refer_code;
        if ($request->has('business_type'))
            $data['business_type'] = $request->business_type;
        if ($request->has('has_webstore'))
            $data['has_webstore'] = $request->has_webstore;
        return $data;
    }

    /**
     * @param $resource
     * @param $data
     * @return Partner
     * @throws ExpenseTrackingServerError
     */
    private function createPartner($resource, $data): Partner
    {
        $data = array_merge($data, [
            "sub_domain" => $this->guessSubDomain($data['name']),
            "affiliation_id" => $this->partnerAffiliation($resource->profile->mobile),
            'referrer_id' => $this->partnerReferral($resource->profile->mobile, $data)
        ]);
        $by = ["created_by" => $resource->id, "created_by_name" => "Resource - " . $resource->profile->name];

        $partner = new Partner();
        $partner = $this->store($resource, $data, $by, $partner);
        if ($partner) {
            if (config('sms.is_on')) {
                (new Sms())
                    ->setFeatureType(FeatureType::REGISTRATION)
                    ->setBusinessType(BusinessType::SMANAGER)
                    ->to($resource->profile->mobile)
                    ->message("অভিনন্দন! sManager-এ আপনি সফল ভাবে রেজিস্ট্রেশন সম্পন্ন করেছেন। বিস্তারিত দেখুন: https://cutt.ly/Training_Video")
                    ->shoot();
            }
            if ($this->ref) $this->referrals::setReference($partner, $this->ref);
            $partner->refer_code = $partner->referCode();
            $partner->save();
        }
        app()->make(ActionRewardDispatcher::class)->run('partner_creation_bonus', $partner, $partner);

        return $partner;
    }

    /**
     * @param $name
     * @return string|string[]|null
     */
    private function guessSubDomain($name)
    {
        $name = strtolower($name);
        $blacklist = ["google", "facebook", "microsoft", "sheba", "sheba.xyz"];

        $is_unicode = (strlen($name) != strlen(utf8_decode($name)));
        if ($is_unicode) $name = "partner-no-name";

        $base_name = $name = preg_replace('/-$/', '', substr(strtolower(clean($name)), 0, 15));
        if ($name == "partner-no-name") $base_name = $name = uniqid("partner-no-name-");
        $already_used = Partner::select('sub_domain')->where('sub_domain', $name)->exists();

        if (in_array($name, $blacklist) || $already_used) {
            $name = uniqid($base_name . '-');
        }
        return $name;
    }

    /**
     * CHECK THIS PARTNER CREATED FROM AFFILIATION,
     * IF FROM AFFILIATION SET PARTNER AFFILIATION ID
     *
     * @param $resource_mobile
     * @return null
     */
    private function partnerAffiliation($resource_mobile)
    {
        $partner_affiliation = PartnerAffiliation::where([
            ['resource_mobile', $resource_mobile], ['status', 'pending']
        ])->first();
        if ($partner_affiliation)
            return $partner_affiliation->id; else return null;
    }

    private function partnerReferral($mobile, $data)
    {
        if (array_key_exists('refer_code', $data)) {
            $refer_code = $data['refer_code'];
            unset($data['refer_code']);
            return $this->setReferenceByCode($refer_code);
        }
        $this->ref = $this->referrals::getReferenceByMobile($mobile);
        if (empty($this->ref)) return null;

        return $this->ref->partner_id;
    }

    private function setReferenceByCode($refer_code)
    {
        $this->ref = $this->referrals::createReferenceByCode($refer_code);
        if (empty($this->ref)) return null;

        return $this->ref->partner_id;
    }

    private function store($resource, $data, $by, $partner)
    {
        DB::transaction(function () use ($resource, $data, $by, &$partner) {
            $partner = $partner->fill(array_merge($data, $by));
            $partner->save();
            $partner->resources()->attach($resource->id, array_merge($by, ['resource_type' => 'Admin']));
            if (isset($data['package_id']) && $data['package_id'] == env('LITE_PACKAGE_ID')) {
                $partner->resources()->attach($resource->id, array_merge($by, ['resource_type' => 'Handyman']));
                (new PartnerRepository($partner))->saveDefaultWorkingHours($by);
            }
            $partner->basicInformations()->save(new PartnerBasicInformation(array_merge($by, ['is_verified' => 0])));
            (new Referral($partner));
            $this->walletSetting($partner, $by);
        });
        $partner = Partner::find($partner->id);
        /** @var UserMigrationService $umService */
        $umService = app(UserMigrationService::class);
        $umService->autoMigrate($partner);
        if (isset($data['billing_type']) && isset($data['package_id']))
            $partner->subscribe($data['package_id'], $data['billing_type']);
        return $partner;
    }


    private function walletSetting($partner, $by)
    {
        PartnerWalletSetting::create(array_merge([
            'partner_id' => $partner->id,
            'security_money' => constants('PARTNER_DEFAULT_SECURITY_MONEY')
        ], $by));
    }

    /**
     * @param $partner
     * @throws ExpenseTrackingServerError
     */
    private function storeExpense($partner)
    {
        $account = $this->entryRepo->createExpenseUser($partner);
        $data = ['expense_account_id' => $account['id']];
        $this->partnerRepo->update($partner, $data);
    }

    /**
     * @throws ExpenseTrackingServerError
     * @throws AccountServerAuthenticationError
     * @throws AccountServerNotWorking
     */
    public function registerByProfile(Request $request)
    {
        ini_set('max_execution_time', 220);
        $this->validate($request, [
            'company_name' => 'required|string',
            'from' => 'string|in:' . implode(',', constants('FROM')),
            'geo' => 'string',
            'name' => 'string',
            'number' => 'string',
            'address' => 'string',
            'gender' => 'string|in:' . Gender::implode(),
            'business_type' => 'string',
            'has_webstore' => 'sometimes|numeric|between:0,1'
        ]);
        /** @var Profile $profile */
        $profile = $request->profile;

        try {
            if (!$resource = $profile->resource) {
                $resource = Resource::create(
                    [
                        'profile_id' => $profile->id,
                        'remember_token' => str_random(60),
                        'status' => $profile->affiliate ? $profile->affiliate->verification_status : 'unverified',
                    ]
                );
            }
        } catch (QueryException $e) {
            $profile->load('resource');
            $resource = $profile->resource;
        }
        $this->setModifier($resource);
        $request['package_id'] = config('sheba.partner_lite_packages_id');
        $request['billing_type'] = 'monthly';
        $request->merge(['number' => $profile->mobile]);
        if ($request->has('name')) $profile->update(['name' => $request->name]);
        if ($request->has('gender')) {
            $profile->update(['gender' => Gender::convertBanglaToEnglish($request->gender)]);
        }
        if ($resource->partnerResources->count() > 0) return api_response($request, null, 403, ['message' => 'You already have a company.']);

        list($partner, $info) = $this->makePartnerGetInfo($request, $profile, $resource);
        return api_response($request, null, 200, ['info' => $info]);
    }

    public function registerByResource(Request $request)
    {
        ini_set('max_execution_time', 220);
        $this->validate($request, [
            'resource_id' => 'required|int',
            'remember_token' => 'required|string',
            'company_name' => 'required|string',
            'from' => 'string|in:' . implode(',', constants('FROM')),
            'package_id' => 'exists:partner_subscription_packages,id',
            'billing_type' => 'in:monthly,yearly'
        ]);

        $resource = Resource::find($request->resource_id);
        if (!($resource && $resource->remember_token == $request->remember_token)) {
            return api_response($request, null, 403, ['message' => "Unauthorized."]);
        }
        $profile = $resource->profile;
        $profile->name = $request->name;
        $profile->save();
        /* if(!$profile->affiliate)
             $this->profileRepository->registerAvatarByKit('affiliate', $profile);*/
        if ($resource->partnerResources->count() > 0)
            return api_response($request, null, 403, ['message' => 'You already have a company!']);
        $request['package_id'] = env('LITE_PACKAGE_ID');
        $request['billing_type'] = 'monthly';
        $request->merge(['number' => $profile->mobile]);
        list($partner, $info) = $this->makePartnerGetInfo($request, $profile, $resource);
        return api_response($request, null, 200, ['info' => $info]);
    }

    public function registerReferAffiliate($affiliate, Request $request)
    {
        ini_set('max_execution_time', 220);
        $this->validate($request, [
            'company_name' => 'required|string',
            'from' => 'string|in:' . implode(',', constants('FROM')),
            'mobile' => 'required|mobile:bd',
            'name' => 'required'
        ]);

        $mobile = formatMobile($request->mobile);
        $request->merge(['phone' => $mobile, 'number' => $mobile]);
        if ($profile = $this->profileRepository->ifExist($mobile, 'mobile')) {
            if ($profile->name === "" || $profile->name === null) {
                $profile->name = $request->name;
                $profile->save();
            }
            $resource = $profile->resource;
            if (!$resource)
                $resource = $this->profileRepository->registerAvatarByKit('resource', $profile);
        } else {
            $profile = $this->profileRepository->registerMobile(array_merge($request->all(), ['mobile' => $mobile]));
            $resource = $this->profileRepository->registerAvatarByKit('resource', $profile);
        }
        $request['package_id'] = env('LITE_PACKAGE_ID');
        $request['billing_type'] = 'monthly';
        $request['affiliate_id'] = (int)$affiliate;
        if (count($resource->partners) > 0) {
            $partnerWithAffiliate = (($resource->partners[0]->affiliate_id === (int)$affiliate) && ($resource->partners[0]->status === 'Onboarded'));
            if (!$partnerWithAffiliate || $this->liteFormCompleted($profile, $resource))
                return api_response($request, null, 403, ['message' => 'This company already referred!']); else {
                $data = $this->makePartnerCreateData($request);
                $data['moderation_status'] = 'pending';
                $partner = $resource->partners[0];
                $partner->update($data);
                $info = $this->profileRepository->getProfileInfo('resource', Profile::find($profile->id));
                return api_response($request, null, 200, ['info' => $info]);
            }
        }
        if ($profile) $request->merge(['number' => $profile->mobile]);
        $data = $this->makePartnerCreateData($request);
        $data['moderation_status'] = 'pending';
        $partner = $this->createPartner($resource, $data);
        if ($partner) return api_response($request, null, 500);

        (new PartnerSubscription())->setRequestedPackage()->setPartner($partner)->createBasicSubscriptionRequest($resource)->updateSubscription();
        $info = $this->profileRepository->getProfileInfo('resource', Profile::find($profile->id));
        return api_response($request, null, 200, ['info' => $info]);
    }

    private function liteFormCompleted($profile, $resource)
    {
        if (count($resource->partners) === 0) return false;

        return $profile->name &&
            $profile->mobile &&
            $profile->pro_pic &&
            $resource->partners[0]->name &&
            $resource->partners[0]->geo_informations &&
            count($resource->partners[0]->categories) > 0;
    }
}
