<?php namespace App\Models;

use App\Exceptions\HyperLocationNotFoundException;
use App\Models\Transport\TransportTicketOrder;
use App\Sheba\InventoryService\Partner\Events\Updated;
use App\Sheba\Payment\Rechargable;
use App\Sheba\UserMigration\UserMigrationService;
use App\Sheba\UserMigration\AccountingUserMigration;
use App\Sheba\UserMigration\UserMigrationRepository;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Sheba\Business\Bid\Bidder;
use Sheba\Checkout\CommissionCalculator;
use Sheba\Dal\BaseModel;
use Sheba\Dal\Complain\Model as Complain;
use Sheba\Dal\PartnerBankInformation\Purposes;
use Sheba\Dal\PartnerDataMigration\PartnerDataMigration;
use Sheba\Dal\PartnerDeliveryInformation\Model as PartnerDeliveryInformation;
use Sheba\Dal\PartnerFinancialInformation\Model as PartnerFinancialInformation;
use Sheba\Dal\PartnerMefInformation\Model as PartnerMefInformation;
use Sheba\Dal\PartnerOrderPayment\PartnerOrderPayment;
use Sheba\Dal\PartnerPosCategory\PartnerPosCategory;
use Sheba\Dal\PartnerWebstoreBanner\Model as PartnerWebstoreBanner;
use Sheba\Dal\GatewayAccount\Model as GatewayAccount;
use Sheba\Dal\PartnerWebstoreDomainInfo\PartnerWebstoreDomainInfo;
use Sheba\Dal\PgwStoreAccount\Model as PgwStoreAccount;
use Sheba\Dal\Survey\Model as Survey;
use Sheba\Dal\UserMigration\UserStatus;
use Sheba\FraudDetection\TransactionSources;
use Sheba\Payment\PayableUser;
use Sheba\Transactions\Types;
use Sheba\Wallet\HasWallet;
use Sheba\Location\Coords;
use Sheba\Location\Distance\Distance;
use Sheba\Location\Distance\DistanceStrategy;
use Sheba\MovieTicket\MovieAgent;
use Sheba\MovieTicket\MovieTicketTrait;
use Sheba\MovieTicket\MovieTicketTransaction;
use Sheba\Partner\BadgeResolver;
use Sheba\Partner\PartnerStatuses;
use Sheba\Wallet\Wallet;
use Sheba\Referral\HasReferrals;
use Sheba\Resource\ResourceTypes;
use Sheba\Reward\Rewardable;
use Sheba\Subscription\Exceptions\InvalidPreviousSubscriptionRules;
use Sheba\Subscription\Partner\PartnerSubscriber;
use Sheba\TopUp\TopUpAgent;
use Sheba\TopUp\TopUpTrait;
use Sheba\TopUp\TopUpTransaction;
use Sheba\Transactions\Wallet\HasWalletTransaction;
use Sheba\Transactions\Wallet\WalletTransactionHandler;
use Sheba\Transport\Bus\BusTicketCommission;
use Sheba\Transport\TransportAgent;
use Sheba\Transport\TransportTicketTransaction;
use Sheba\Voucher\Contracts\CanApplyVoucher;
use Sheba\Voucher\VoucherCodeGenerator;
use Sheba\Dal\Category\Category;
use Sheba\Dal\Service\Service;
use Sheba\Dal\PartnerNeoBankingInfo\Model as PartnerNeoBankingInfo;
use Sheba\Dal\PartnerNeoBankingAccount\Model as PartnerNeoBankingAccount;
use Sheba\Dal\UserMigration\Model as UserMigration;


class Partner extends BaseModel implements Rewardable, TopUpAgent, HasWallet, TransportAgent, CanApplyVoucher, MovieAgent, Rechargable, Bidder, HasWalletTransaction, HasReferrals, PayableUser
{
    const NOT_ELIGIBLE = 'not_eligible';
    use Wallet, TopUpTrait, MovieTicketTrait;

    public $totalCreditForSubscription;
    public $totalPriceRequiredForSubscription;
    public $creditBreakdown;
    protected $guarded = ['id', 'original_created_at'];
    protected $dates = [
        'last_billed_date',
        'billing_start_date',
        'original_created_at'
    ];
    protected $casts = [
        'wallet' => 'double',
        'last_billed_amount' => 'double',
        'reward_point' => 'int',
        'current_impression' => 'double',
        'impression_limit' => 'double',
        'uses_sheba_logistic' => 'int',
        'can_topup' => 'int',
        'delivery_charge' => 'double',
    ];
    protected $resourcePivotColumns = [
        'id',
        'designation',
        'department',
        'resource_type',
        'is_verified',
        'verification_note',
        'created_by',
        'created_by_name',
        'created_at',
        'updated_by',
        'updated_by_name',
        'updated_at'
    ];
    protected $categoryPivotColumns = [
        'id',
        'experience',
        'preparation_time_minutes',
        'response_time_min',
        'response_time_max',
        'commission',
        'is_verified',
        'uses_sheba_logistic',
        'verification_note',
        'created_by',
        'created_by_name',
        'created_at',
        'updated_by',
        'updated_by_name',
        'updated_at',
        'is_home_delivery_applied',
        'is_partner_premise_applied',
        'delivery_charge'
    ];
    protected $servicePivotColumns = [
        'id',
        'description',
        'options',
        'prices',
        'min_prices',
        'base_prices',
        'base_quantity',
        'is_published',
        'discount',
        'discount_start_date',
        'discount_start_date',
        'is_verified',
        'verification_note',
        'created_by',
        'created_by_name',
        'created_at',
        'updated_by',
        'updated_by_name',
        'updated_at'
    ];
    private $resourceTypes;

    public static $updatedEventClass = Updated::class;

    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->resourceTypes = constants('RESOURCE_TYPES');
    }

    public function basicInformations()
    {
        return $this->hasOne(PartnerBasicInformation::class);
    }

    public function partnerMefInformation()
    {
        return $this->hasOne(PartnerMefInformation::class);
    }


    public function financeResources()
    {
        return $this->belongsToMany(Resource::class)->where('resource_type', constants('RESOURCE_TYPES')['Finance'])->withPivot($this->resourcePivotColumns);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class)->withPivot($this->servicePivotColumns);
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class);
    }

    public function businesses()
    {
        return $this->belongsToMany(Business::class, 'business_partners');
    }

    public function categoryRequests()
    {
        return $this->hasMany(CategoryRequest::class);
    }

    public function getLocationsList()
    {
        return $this->locations->lists('id')->toArray();
    }

    public function getLocationsNames()
    {
        return $this->locations->lists('name')->toArray();
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function orders()
    {
        return $this->hasMany(PartnerOrder::class);
    }

    public function jobs()
    {
        return $this->hasManyThrough(Job::class, PartnerOrder::class);
    }

    public function complains()
    {
        return $this->hasMany(Complain::class);
    }

    public function payments()
    {
        return $this->hasManyThrough(PartnerOrderPayment::class, PartnerOrder::class);
    }

    public function partner_orders()
    {
        return $this->hasMany(PartnerOrder::class);
    }

    public function partnerOrders()
    {
        return $this->hasMany(PartnerOrder::class);
    }

    public function todayOrders()
    {
        return $this->hasMany(PartnerOrder::class)->whereDate('created_at', '=', Carbon::today());
    }

    public function walletSetting()
    {
        return $this->hasOne(PartnerWalletSetting::class);
    }

    public function workingHours()
    {
        return $this->hasMany(PartnerWorkingHour::class);
    }

    public function dailyStats()
    {
        return $this->hasMany(PartnerDailyStat::class);
    }

    public function topups()
    {
        return $this->hasMany(TopUpOrder::class, 'agent_id')->where('agent_type', 'App\\Models\\Partner');
    }

    public function commission($service_id)
    {
        $service_category = Service::find($service_id)->category;
        $commissions = (new CommissionCalculator())->setCategory($service_category)->setPartner($this);
        return $commissions->getServiceCommission();
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class)->withPivot($this->categoryPivotColumns);
    }

    public function loan()
    {
        return $this->hasMany(PartnerBankLoan::class);
    }

    public function onIndefiniteLeave()
    {
        $leave = $this->runningLeave();
        return ($leave && !$leave->end_date) ? true : false;
    }

    public function runningLeave($date = null)
    {
        $date = ($date) ? (($date instanceof Carbon) ? $date : new Carbon($date)) : Carbon::now();
        foreach ($this->leaves()->whereDate('start', '<=', $date)->get() as $leave) {
            if ($leave->isRunning($date))
                return $leave;
        }
        return null;
    }

    public function leaves()
    {
        return $this->hasMany(PartnerLeave::class);
    }

    public function webstoreDomain()
    {
        return $this->hasOne(PartnerWebstoreDomainInfo::class);
    }

    public function shebaCredit()
    {
        return $this->wallet + $this->shebaBonusCredit();
    }

    public function shebaBonusCredit()
    {
        return (double)$this->bonuses()->where('status', 'valid')->sum('amount');
    }

    public function bonuses()
    {
        return $this->morphMany(Bonus::class, 'user');
    }

    public function hasLeave($date)
    {
        $date = $date == null ? Carbon::now() : new Carbon($date);
        foreach ($this->leaves as $leave) {
            if ($date->between($leave->start, $leave->end)) {
                return true;
            }
        }
        return false;
    }

    public function getIdentityAttribute()
    {
        if ($this->name != '') {
            return $this->name;
        } elseif ($this->mobile) {
            return $this->mobile;
        }
        return $this->email;
    }

    public function getContactResource()
    {
        if ($admin_resource = $this->getFirstAdminResource())
            return $admin_resource;
        return null;
    }

    public function getFirstOperationResource()
    {
        if ($this->resources) {
            return $this->resources->where('pivot.resource_type', ResourceTypes::OPERATION)->first();
        } else {
            return $this->admins->first();
        }
    }

    public function getFirstAdminResource()
    {
        if ($this->resources) {
            return $this->resources->where('pivot.resource_type', ResourceTypes::ADMIN)->first();
        } else {
            return $this->admins->first();
        }
    }

    public function generateReferral()
    {
        return VoucherCodeGenerator::byName($this->name);
    }

    public function scopePublished($query)
    {
        return $query->where('status', PartnerStatuses::VERIFIED);
    }

    public function scopeVerified($query)
    {
        return $query->where('status', PartnerStatuses::VERIFIED);
    }

    public function isVerified()
    {
        return $this->status === PartnerStatuses::VERIFIED;
    }

    public function getContactNumber()
    {
        $resource = $this->getContactResource();
        if (!$resource) return null;
        return $resource->profile->mobile;
    }

    public function getTinNumber()
    {
        $resource = $this->getFirstAdminResource();
        if (!$resource) return null;
        return $resource->profile->tin_no;
    }

    public function getContactResourceProPic()
    {
        $resource = $this->getContactResource();
        if (!$resource) return null;
        return $resource->profile->pro_pic;
    }

    public function getContactEmail()
    {
        $resource = $this->getContactResource();
        if (!$resource) return null;
        return $resource->profile->email;
    }

    public function isNIDVerified()
    {
        if ($operation_resource = $this->operationResources()->first())
            return $operation_resource->profile->nid_verified;
        if ($admin_resource = $this->admins()->first())
            return $admin_resource->profile->nid_verified;
        return null;
    }

    public function getNidNo()
    {
        if ($operation_resource = $this->operationResources()->first())
            return $operation_resource->profile->nid_no;
        if ($admin_resource = $this->admins()->first())
            return $admin_resource->profile->nid_no;
        return null;
    }

    public function getDob()
    {
        if ($operation_resource = $this->operationResources()->first())
            return $operation_resource->profile->dob;
        if ($admin_resource = $this->admins()->first())
            return $admin_resource->profile->dob;
        return null;
    }

    public function operationResources()
    {
        return $this->belongsToMany(Resource::class)->where('resource_type', constants('RESOURCE_TYPES')['Operation'])->withPivot($this->resourcePivotColumns);
    }

    public function admins()
    {
        return $this->belongsToMany(Resource::class)->where('resource_type', constants('RESOURCE_TYPES')['Admin'])->withPivot($this->resourcePivotColumns);
    }


    public function updatedAt()
    {
        if ($operation_resource = $this->operationResources()->first())
            return $operation_resource->profile->updated_at;
        if ($admin_resource = $this->admins()->first())
            return $admin_resource->profile->updated_at;
        return null;
    }

    public function getContactPerson()
    {
        if ($admin_resource = $this->getAdmin())
            return $admin_resource->profile->name;
        return null;
    }

    public function getAdmin()
    {
        if ($admin_resource = $this->admins()->first())
            return $admin_resource;
        return null;
    }

    public function getManagerMobile()
    {
        if ($operation_resource = $this->resources->where('pivot.resource_type', constants('RESOURCE_TYPES')['Operation'])->first()) {
            return $operation_resource->profile->mobile;
        } elseif ($admin_resource = $this->resources->where('pivot.resource_type', constants('RESOURCE_TYPES')['Admin'])->first()) {
            return $admin_resource->profile->mobile;
        }
        return null;
    }

    public function hasThisResource($resource_id, $type)
    {
        return $this->resources->where('id', (int)$resource_id)->where('pivot.resource_type', $type)->first() ? true : false;
    }

    public function transactions()
    {
        return $this->hasMany(PartnerTransaction::class);
    }

    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    public function withdrawalRequests()
    {
        Relation::morphMap(['partner' => 'App\Models\Partner']);
        return $this->morphMany(WithdrawalRequest::class, 'requester');
    }

    public function lastWeekWithdrawalRequest()
    {
        return $this->withdrawalRequests()->lastWeek()->notCancelled()->first();
    }

    public function currentWeekWithdrawalRequest()
    {
        return $this->withdrawalRequests()->currentWeek()->notCancelled()->first();
    }

    public function onGoingJobs()
    {
        return $this->jobs()->whereIn('status', [
            constants('JOB_STATUSES')['Accepted'],
            constants('JOB_STATUSES')['Process'],
            constants('JOB_STATUSES')['Schedule_Due']
        ])->count();
    }

    public function resourcesInCategory($category)
    {
        $category = $category instanceof Category ? $category->id : $category;
        $partner_resource_ids = [];
        $this->handymanResources()->verified()->get()->map(function ($resource) use (&$partner_resource_ids) {
            $partner_resource_ids[$resource->pivot->id] = $resource;
        });
        $result = [];
        collect(DB::table('category_partner_resource')->select('partner_resource_id')->whereIn('partner_resource_id', array_keys($partner_resource_ids))->where('category_id', $category)->get())->pluck('partner_resource_id')->each(function ($partner_resource_id) use ($partner_resource_ids, &$result) {
            $result[] = $partner_resource_ids[$partner_resource_id];
        });
        return collect($result);
    }

    public function handymanResources()
    {
        return $this->belongsToMany(Resource::class)->where('resource_type', constants('RESOURCE_TYPES')['Handyman'])->withPivot($this->resourcePivotColumns);
    }

    public function isCreditLimitExceed()
    {
        return !$this->hasAppropriateCreditLimit();
    }

    public function hasAppropriateCreditLimit()
    {
        return (double)$this->wallet >= (double)$this->walletSetting->min_wallet_threshold;
    }

    public function bankInfos()
    {
        return $this->hasMany(PartnerBankInformation::class);
    }

    public function bankInformations()
    {
        return $this->bankInfos()->where('purpose', Purposes::GENERAL);
    }

    public function withdrawalBankInformations()
    {
        return $this->bankInfos()->where('purpose', Purposes::PARTNER_WALLET_WITHDRAWAL);
    }

    public function neoBankAccount()
    {
        return $this->hasMany(PartnerNeoBankingAccount::class);
    }

    public function neoBankInfo()
    {
        return $this->hasOne(PartnerNeoBankingInfo::class, 'partner_id', 'id');
    }

    public function affiliation()
    {
        return $this->belongsTo(PartnerAffiliation::class, 'affiliation_id');
    }

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function totalWalletAmount()
    {
        return (double)$this->wallet + $this->bonusWallet();
    }

    public function bonusWallet()
    {
        return (double)$this->bonuses()->where('status', 'valid')->sum('amount');
    }

    public function bonusLogs()
    {
        return $this->morphMany(BonusLog::class, 'user');
    }

    public function subscription()
    {
        return $this->belongsTo(PartnerSubscriptionPackage::class, 'package_id');
    }

    public function subscriptionDiscount()
    {
        return $this->belongsTo(PartnerSubscriptionPackageDiscount::class, 'discount_id');
    }

    public function currentSubscription()
    {
        return $this->subscription->where('id', $this->package_id)->first();
    }

    public function subscriptionOrders()
    {
        return $this->hasMany(SubscriptionOrder::class);
    }

    public function getSubscriptionRulesAttribute($rules)
    {
        $rules = json_decode($rules);
        return is_string($rules) ? json_decode($rules) : $rules;
    }

    public function subscribe($package, $billing_type)
    {
        $package = $package ? (($package) instanceof PartnerSubscriptionPackage ? $package : PartnerSubscriptionPackage::find($package)) : $this->subscription;
        $discount = $package->runningDiscount($billing_type);
        $discount_id = $discount ? $discount->id : null;
        $this->subscriber()->getPackage($package)->subscribe($billing_type, $discount_id);
    }

    public function subscriber()
    {
        return new PartnerSubscriber($this);
    }

    /**
     * @param $package
     * @param null $upgradeRequest
     * @param int $sms
     * @throws Exception
     */
    public function subscriptionUpgrade($package, $upgradeRequest = null, $sms = 1)
    {
        $package = $package ? (($package) instanceof PartnerSubscriptionPackage ? $package : PartnerSubscriptionPackage::find($package)) : $this->subscription;
        $this->subscriber()->setSMSNotification($sms)->upgrade($package, $upgradeRequest);
    }

    public function getBonusCreditAttribute()
    {
        return (double)$this->bonuses()->valid()->sum('amount');
    }

    public function runSubscriptionBilling()
    {
        $this->subscriber()->getBilling()->runSubscriptionBilling();
    }


    public function retailers()
    {
        return $this->getFirstAdminResource()->retailers();
    }

    public function movieTicketOrders()
    {
        return $this->morphMany(MovieTicketOrder::class, 'agent');
    }

    public function getCommissionAttribute()
    {
        return (double)$this->subscription_rules->commission->value;
    }

    public function canCreateResource(array $types)
    {
        return $this->subscriber()->canCreateResource($types);
    }

    public function subscriptionUpdateRequest()
    {
        return $this->hasMany(PartnerSubscriptionUpdateRequest::class);
    }

    public function canRequestForSubscriptionUpdate()
    {
        return !(PartnerSubscriptionUpdateRequest::status(constants('PARTNER_PACKAGE_UPDATE_STATUSES')['Pending'])->partner($this->id)->count());
    }

    public function lastSubscriptionUpdateRequest()
    {
        return PartnerSubscriptionUpdateRequest::status(constants('PARTNER_PACKAGE_UPDATE_STATUSES')['Pending'])->partner($this->id)->get()->last();
    }

    public function isFirstTimeVerified()
    {
        return $this->statusChangeLogs()->where('to', PartnerStatuses::VERIFIED)->count() == 0;
    }

    public function statusChangeLogs()
    {
        return $this->hasMany(PartnerStatusChangeLog::class);
    }

    public function impressionDeductions()
    {
        return $this->hasMany(ImpressionDeduction::class);
    }

    public function topUpTransaction(TopUpTransaction $transaction)
    {
        return (new WalletTransactionHandler())->setModel($this)->setAmount($transaction->getAmount())
            ->setSource(TransactionSources::TOP_UP)->setType(Types::debit())->setLog($transaction->getLog())
            ->store();
    }

    public function todayJobs($jobs = null)
    {
        if (is_null($jobs)) {
            return $this->notCancelledJobs()->filter(function ($job, $key) {
                return $job->schedule_date == Carbon::now()->toDateString() && !in_array($job->status, [
                        'Served',
                        'Cancelled',
                        'Declined',
                        'Not Responded'
                    ]);
            });
        }
        return $jobs->filter(function ($job, $key) {
            return $job->schedule_date == Carbon::now()->toDateString() && !in_array($job->status, [
                    'Served',
                    'Cancelled',
                    'Declined',
                    'Not Responded'
                ]);
        });
    }

    public function notCancelledJobs()
    {
        return $this->jobs()->whereNotExists(function ($q) {
            $q->from('job_cancel_requests')->whereRaw('job_id = jobs.id');
        })->select('jobs.id', 'schedule_date', 'status')->get();

    }

    public function tomorrowJobs($jobs = null)
    {
        if (is_null($jobs)) {
            return $this->notCancelledJobs()->filter(function ($job, $key) {
                return $job->schedule_date == Carbon::tomorrow()->toDateString() && !in_array($job->status, [
                        'Served',
                        'Cancelled',
                        'Declined'
                    ]);
            });
        }
        return $jobs->filter(function ($job, $key) {
            return $job->schedule_date == Carbon::tomorrow()->toDateString() && !in_array($job->status, [
                    'Served',
                    'Cancelled',
                    'Declined'
                ]);
        });
    }

    public function notRespondedJobs($jobs = null)
    {
        if (is_null($jobs))
            return $this->notCancelledJobs()->where('status', constants('JOB_STATUSES')['Not_Responded']);
        return $jobs->where('status', constants('JOB_STATUSES')['Not_Responded']);
    }

    public function scheduleDueJobs($jobs = null)
    {
        if (is_null($jobs))
            return $this->notCancelledJobs()->where('status', constants('JOB_STATUSES')['Schedule_Due']);
        return $jobs->where('status', constants('JOB_STATUSES')['Schedule_Due']);
    }

    public function serveDueJobs($jobs = null)
    {
        if (is_null($jobs))
            return $this->notCancelledJobs()->where('status', constants('JOB_STATUSES')['Serve_Due']);
        return $jobs->where('status', constants('JOB_STATUSES')['Serve_Due']);
    }

    public function getCommission()
    {
        return new \Sheba\TopUp\Commission\Partner();
    }

    /**
     * @return mixed
     * @throws HyperLocationNotFoundException
     */
    public function getHyperLocation()
    {
        $geo = json_decode($this->geo_informations);
        if (empty($geo)) {
            throw  new HyperLocationNotFoundException();
        }
        return HyperLocal::insidePolygon($geo->lat, $geo->lng)->first();
    }

    public function hasCoverageOn(Coords $coords)
    {
        $geo = json_decode($this->geo_informations);
        $partner_coord = new Coords(floatval($geo->lat), floatval($geo->lng));
        $distance = (new Distance(DistanceStrategy::$VINCENTY))->linear();
        return $distance->to($coords)->from($partner_coord)->isWithin($geo->radius * 1000);
    }

    public function geoChangeLogs()
    {
        return $this->hasMany(PartnerGeoChangeLog::class);
    }

    public function isLite()
    {
        return $this->package_id == (int)config('sheba.partner_lite_packages_id');
    }
    public function isMxPartner(){
        return (int)$this->package_id === (int) config('sheba.mx_package_id');
    }

    public function isAccessibleForMarketPlace()
    {
        return !in_array($this->package_id, config('sheba.marketplace_not_accessible_packages_id'));
    }

    public function scopeLite($q)
    {
        return $q->where('package_id', (int)config('sheba.partner_lite_packages_id'));
    }

    public function scopeModerated($query)
    {
        return $query->where('moderator_id', '<>', null)->where('moderation_status', 'approved');
    }

    public function servingMasterCategories()
    {
        $this->load([
            'categories' => function ($q) {
                $q->with([
                    'parent' => function ($q) {
                        $q->select('id', 'categories.name');
                    }
                ]);
            }
        ]);
        return $this->categories->pluck('parent.name')->unique()->implode(', ');
    }

    public function servingMasterCategoryIds()
    {
        return array_unique($this->categories->pluck('parent_id')->toArray());
    }

    public function resolveBadge()
    {
        return (new BadgeResolver())->setPartner($this)->resolveVersionWiseBadge()->getBadge();
    }

    public function resolveSubscriptionType()
    {
        return (new BadgeResolver())->setPartner($this)->resolveVersionWiseBadge()->getSubscriptionType();
    }

    public function getTopFiveResources()
    {
        return $this->resources()->reviews()->groupBy('resource_id')->orderBy('avg(reviews.rating)')->select('id, avg(reviews.rating)')->get();
    }

    public function resources()
    {
        return $this->belongsToMany(Resource::class)->withPivot($this->resourcePivotColumns);
    }

    public function businessAdditionalInformation()
    {
        return json_decode($this->business_additional_information);
    }

    public function salesInformation()
    {
        return json_decode($this->sales_information);
    }

    public function posServices()
    {
        return $this->hasMany(PartnerPosService::class);
    }

    public function posOrders()
    {
        return $this->hasMany(PosOrder::class);
    }

    public function posSetting()
    {
        return $this->hasOne(PartnerPosSetting::class);
    }

    public function getMobile()
    {
        return $this->getContactNumber();
    }

    public function isAllowedToSendWithdrawalRequest()
    {
        return !($this->withdrawalRequests()->active()->count() > 0);
    }

    /**
     * @return BusTicketCommission|\Sheba\Transport\Bus\Commission\Partner
     */
    public function getBusTicketCommission()
    {
        return new \Sheba\Transport\Bus\Commission\Partner();
    }

    public function transportTicketTransaction(TransportTicketTransaction $transaction)
    {
        (new WalletTransactionHandler())->setModel($this)->setAmount($transaction->getAmount())->setSource(TransactionSources::TRANSPORT)->setType(Types::credit())->setLog($transaction->getLog())->dispatch();
    }

    public function transportTicketOrders()
    {
        return $this->morphMany(TransportTicketOrder::class, 'agent');
    }

    public function movieTicketTransaction(MovieTicketTransaction $transaction)
    {
        (new WalletTransactionHandler())->setModel($this)->setAmount($transaction->getAmount())->setSource(TransactionSources::MOVIE)->setType(Types::debit())->setLog($transaction->getLog())->dispatch();
    }

    public function movieTicketTransactionNew(MovieTicketTransaction $transaction)
    {
        (new WalletTransactionHandler())->setModel($this)->setAmount($transaction->getAmount())->setSource(TransactionSources::MOVIE)->setType(Types::credit())->setLog($transaction->getLog())->dispatch();
    }

    public function getMovieTicketCommission()
    {
        return new \Sheba\MovieTicket\Commission\Partner();
    }

    /**
     * @param PartnerSubscriptionPackage $package
     * @param                            $billingType
     * @param int $billingCycle
     * @return bool
     * @throws InvalidPreviousSubscriptionRules
     */
    public function hasCreditForSubscription(PartnerSubscriptionPackage $package, $billingType, $billingCycle = 1)
    {
        $this->totalPriceRequiredForSubscription = $package->originalPrice($billingType) - (double)$package->discountPrice($billingType, $billingCycle);
        $this->totalCreditForSubscription = $this->getTotalCreditExistsForSubscription();
        return $this->totalCreditForSubscription >= $this->totalPriceRequiredForSubscription;
    }

    /** @return float|int
     * @throws InvalidPreviousSubscriptionRules
     */
    public function getTotalCreditExistsForSubscription()
    {
        list($remaining, $wallet, $bonus_wallet, $threshold) = $this->getCreditBreakdown();
        return round($bonus_wallet + $wallet) - $threshold;
    }

    /**
     * @return array
     */
    public function getCreditBreakdown()
    {
        $remaining = (double)0;
        $wallet = (double)$this->wallet;
        $bonus_wallet = (double)$this->bonusWallet();
        $threshold = $this->walletSetting ? (double)$this->walletSetting->min_wallet_threshold : 0;
        $freeze_money = $this->walletSetting ? (double)$this->walletSetting->pending_withdrawal_amount : 0;
        $this->creditBreakdown = [
            'remaining_subscription_charge' => $remaining,
            'wallet' => $wallet,
            'threshold' => $threshold,
            'bonus_wallet' => $bonus_wallet,
            'freeze_money' => $freeze_money
        ];
        return [
            $remaining,
            $wallet,
            $bonus_wallet,
            $threshold
        ];
    }

    public function isAlreadyCollectedAdvanceSubscriptionFee()
    {
        $last_advance_subscription_package_charge = $this->lastAdvanceSubscriptionCharge();
        if (empty($last_advance_subscription_package_charge)) return false;
        return $this->isValidAdvancePayment($last_advance_subscription_package_charge);
    }

    public function invalidateAdvanceSubscriptionFee()
    {
        $charge = $this->lastAdvanceSubscriptionCharge();
        if (!empty($charge)) {
            $charge->is_valid_advance_payment = 0;
            $charge->save();
        }
    }

    private function lastAdvanceSubscriptionCharge()
    {
        return $this->subscriptionPackageCharges()->where('advance_subscription_fee', 1)->where('is_valid_advance_payment')->orderBy('id', 'desc')->first();
    }

    public function isValidAdvancePayment($last_subscription_package_charge)
    {
        return $last_subscription_package_charge->advance_subscription_fee && $last_subscription_package_charge->is_valid_advance_payment;
    }

    public function alreadyCollectedSubscriptionFee()
    {
        if (!$this->isAlreadyCollectedAdvanceSubscriptionFee()) return 0;
        $last_subscription_package_charge = $this->lastAdvanceSubscriptionCharge();
        if (empty($last_subscription_package_charge) || !$this->isValidAdvancePayment($last_subscription_package_charge)) return 0;
        return (double)$last_subscription_package_charge->package_price;
    }

    public function subscriptionPackageCharges()
    {
        return $this->hasMany(PartnerSubscriptionPackageCharge::class);
    }

    public function periodicBillingHandler()
    {
        return $this->subscriber()->periodicBillingHandler();
    }

    public function getStatusToCalculateAccess()
    {
        return PartnerStatuses::getStatusToCalculateAccess($this->status);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(PartnerReferral::class, 'partner_id', 'id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'referrer_id', 'id');
    }

    public function usage(): HasMany
    {
        return $this->hasMany(PartnerUsageHistory::class, 'partner_id', 'id');
    }

    public function referCode()
    {
        return $this->id . str_random(8 - (strlen($this->id)));
    }

    public function isMissionSaveBangladesh()
    {
        return $this->id == config('sheba.mission_save_bangladesh_partner_id');
    }

    public function canTopup()
    {
        return $this->can_topup == 1;
    }

    public function posCategories()
    {
        return $this->hasMany(PartnerPosCategory::class);

    }


    public function webstoreBanner()
    {
        return $this->hasOne(PartnerWebstoreBanner::class);
    }

    public function dataMigration()
    {
        return $this->hasOne(PartnerDataMigration::class);
    }

    public function topupChangeLogs()
    {
        return $this->hasMany(CanTopUpUpdateLog::class);
    }

    public function deliveryInformation()
    {
        return $this->hasOne(PartnerDeliveryInformation::class);
    }

    public function getGatewayChargesId()
    {
        return $this->subscription_rules->payment_gateway_configuration_id;
    }

    public function userMigration()
    {
        return $this->hasMany(UserMigration::class, 'user_id');
    }

    public function lastUpdatedUserMigration()
    {
        return $this->userMigration->max('updated_at') ?? null;
    }

    public function lastUpdatedSubscription()
    {
        return $this->subscription->updated_at ?? null;
    }

    public function lastUpdatedPosSetting()
    {
        return $this->posSetting->updated_at ?? null;
    }

    public function lastBilledDate()
    {
        return $this->last_billed_date ?? null;
    }

    /**
     * @throws Exception
     */
    public function isMigrated($module_name): bool
    {
        return true;
    }

    public function pgwGatewayAccounts()
    {
        Relation::morphMap([
            'partner' => 'App\Models\Partner',
        ]);
        return $this->morphMany(GatewayAccount::class, 'user')->where('gateway_type', 'pgw');
    }

    public function QRGatewayAccounts()
    {
        Relation::morphMap([
            'partner' => 'App\Models\Partner',
        ]);
        return $this->morphMany(GatewayAccount::class, 'user')->where('gateway_type', 'qr');
    }

    public function lastUpdatedPGWStore()
    {
        return $this->pgwGatewayAccounts->max('updated_at') ?? null;
    }

    public function lastResourceUpdated()
    {
        return $this->getFirstAdminResource()->updated_at;
    }

    public function survey()
    {
        return $this->morphMany(Survey::class, 'user');
    }

    public function financialInformations()
    {
        return $this->hasOne(PartnerFinancialInformation::class);
    }
}
