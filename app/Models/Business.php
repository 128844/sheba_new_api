<?php namespace App\Models;

use App\Sheba\Business\Attendance\HalfDaySetting\HalfDayType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Sheba\Business\AttendanceActionLog\TimeByBusiness;
use Sheba\Business\CoWorker\Statuses;
use Sheba\Dal\BaseModel;
use Sheba\Dal\BusinessAttendanceTypes\AttendanceTypes;
use Sheba\Dal\LeaveType\Model as LeaveTypeModel;
use Sheba\FraudDetection\TransactionSources;
use Sheba\Helpers\TimeFrame;
use Sheba\ModificationFields;
use Sheba\Payment\PayableUser;
use Sheba\Transactions\Types;
use Sheba\Wallet\Wallet;
use Sheba\TopUp\TopUpAgent;
use Sheba\TopUp\TopUpTrait;
use Sheba\TopUp\TopUpTransaction;
use Sheba\Transactions\Wallet\HasWalletTransaction;
use Sheba\Transactions\Wallet\WalletTransactionHandler;
use Sheba\Dal\BusinessAttendanceTypes\Model as BusinessAttendanceType;

use Sheba\Wallet\WalletUpdateEvent;
use Sheba\Dal\BusinessOffice\Model as BusinessOffice;
use Sheba\Dal\BusinessOfficeHours\Model as BusinessOfficeHour;
use Sheba\Dal\BusinessAttendanceTypes\Model as BusinessAttendanceTypes;

class Business extends BaseModel implements TopUpAgent, PayableUser, HasWalletTransaction
{
    use Wallet, ModificationFields, TopUpTrait;

    protected $guarded = ['id'];
    const BUSINESS_FISCAL_START_MONTH = 7;

    public function offices()
    {
        return $this->hasMany(BusinessOffice::class);
    }

    public function members()
    {
        return $this->belongsToMany(Member::class)->withTimestamps();
    }

    public function membersWithProfileAndAccessibleBusinessMember()
    {
        return $this->members()->select('members.id', 'profile_id')->with([
            'profile' => function ($q) {
                $q->select('profiles.id', 'name', 'mobile', 'email', 'pro_pic');
            }, 'businessMember' => function ($q) {
                $q->select('business_member.id', 'business_id', 'member_id', 'type', 'business_role_id', 'status')->with([
                    'role' => function ($q) {
                        $q->select('business_roles.id', 'business_department_id', 'name')->with([
                            'businessDepartment' => function ($q) {
                                $q->select('business_departments.id', 'business_id', 'name');
                            }
                        ]);
                    }
                ]);
            }
        ])->wherePivot('status', '<>', Statuses::INACTIVE);
    }

    public function getAccessibleBusinessMember()
    {
        return BusinessMember::where('business_id', $this->id)->where('status', '<>', Statuses::INACTIVE)->with([
            'member' => function ($q) {
                $q->select('members.id', 'profile_id')->with([
                    'profile' => function ($q) {
                        $q->select('profiles.id', 'name', 'mobile', 'email', 'pro_pic');
                    }
                ]);
            }, 'role' => function ($q) {
                $q->select('business_roles.id', 'business_department_id', 'name')->with([
                    'businessDepartment' => function ($q) {
                        $q->select('business_departments.id', 'business_id', 'name');
                    }
                ]);
            }
        ]);
    }

    /**
     * @return array
     */
    public function getBusinessMemberProrate()
    {
        $business_member_leave_types = [];
        $this->getAccessibleBusinessMember()->get()->each(function ($business_member) use (&$business_member_leave_types) {
            $leave_types = [];
            if (!$business_member->leaveTypes->isEmpty()) {
                $business_member->leaveTypes->each(function ($leave_type) use (&$leave_types) {
                    $leave_types[$leave_type->leave_type_id] = ['total_days' => $leave_type->total_days];
                });
                $business_member_leave_types[$business_member->id] = ['leave_types' => $leave_types];
            }
        });
        return $business_member_leave_types;
    }

    public function businessSms()
    {
        return $this->hasMany(BusinessSmsTemplate::class);
    }

    public function partners()
    {
        return $this->belongsToMany(Partner::class, 'business_partners');
    }

    public function officeHour()
    {
        return $this->hasOne(BusinessOfficeHour::class);
    }

    public function activePartners()
    {
        return $this->partners()->where('is_active_for_b2b', 1);
    }

    public function deliveryAddresses()
    {
        return $this->hasMany(BusinessDeliveryAddress::class);
    }

    public function bankInformations()
    {
        return $this->hasMany(BusinessBankInformations::class);
    }

    public function joinRequests()
    {
        return $this->morphMany(JoinRequest::class, 'organization');
    }

    public function businessCategory()
    {
        return $this->belongsTo(BusinessCategory::class);
    }

    public function businessTrips()
    {
        return $this->hasMany(BusinessTrip::class);
    }

    public function businessTripRequests()
    {
        return $this->hasMany(BusinessTripRequest::class);
    }

    public function bonusLogs()
    {
        return $this->morphMany(BonusLog::class, 'user');
    }

    public function topups()
    {
        return $this->hasMany(TopUpOrder::class, 'agent_id')->where('agent_type', 'App\\Models\\Business');
    }

    public function movieTicketOrders()
    {
        return $this->morphMany(MovieTicketOrder::class, 'agent');
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

    public function transactions()
    {
        return $this->hasMany(BusinessTransaction::class);
    }

    public function vehicles()
    {
        return $this->morphMany(Vehicle::class, 'owner');
    }

    public function businessSmsTemplates()
    {
        return $this->hasMany(BusinessSmsTemplate::class, 'business_id');
    }

    public function procurements()
    {
        return $this->morphMany(Procurement::class, 'owner');
    }

    public function hiredVehicles()
    {
        return $this->morphMany(HiredVehicle::class, 'hired_by');
    }

    public function hiredDrivers()
    {
        return $this->morphMany(HiredDriver::class, 'hired_by');
    }

    public function getCommission()
    {
        return new \Sheba\TopUp\Commission\Business();
    }

    public function topUpTransaction(TopUpTransaction $transaction)
    {
        /*
         * WALLET TRANSACTION NEED TO REMOVE
         * $this->debitWallet($transaction->getAmount());
        $this->walletTransaction(['amount' => $transaction->getAmount(), 'type' => 'Debit', 'log' => $transaction->getLog()]);*/
        (new WalletTransactionHandler())->setModel($this)->setAmount($transaction->getAmount())->setType(Types::debit())->setLog($transaction->getLog())
            ->setSource(TransactionSources::TOP_UP)->dispatch();
    }

    public function getMobile()
    {
        return '+8801678242934';
    }

    public function getContactPerson()
    {
        if ($super_admin = $this->getAdmin()) return $super_admin->profile->name;
        return null;
    }

    public function getContactNumber()
    {
        if ($super_admin = $this->getAdmin()) return $super_admin->profile->mobile;
        return null;
    }

    public function getContactEmail()
    {
        if ($super_admin = $this->getAdmin()) return $super_admin->profile->email;
        return null;
    }

    public function getAdmin()
    {
        if ($super_admin = $this->superAdmins()->first()) return $super_admin;
        return null;
    }

    public function superAdmins()
    {
        return $this->belongsToMany(Member::class)->where('is_super', 1);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function leaveTypes()
    {
        return $this->hasMany(LeaveTypeModel::class);
    }

    public function attendanceTypes()
    {
        return $this->hasMany(BusinessAttendanceType::class);
    }

    public function getBusinessFiscalPeriod()
    {
        $business_fiscal_start_month = $this->fiscal_year ?: Business::BUSINESS_FISCAL_START_MONTH;
        $time_frame = new TimeFrame();
        return $time_frame->forAFiscalYear(Carbon::now(), $business_fiscal_start_month);
    }

    public function isRemoteAttendanceEnable()
    {
        if (in_array(AttendanceTypes::REMOTE, $this->attendanceTypes->pluck('attendance_type')->toArray())) return true;
        return false;
    }

    public function getBusinessHalfDayConfiguration()
    {
        return json_decode($this->half_day_configuration, 1);
    }

    public function halfDayStartEnd($which_half)
    {
        return $this->getBusinessHalfDayConfiguration()[$which_half];
    }

    public function halfDayStartTimeUsingWhichHalf($which_half)
    {
        return $this->getBusinessHalfDayConfiguration()[$which_half]['start_time'];
    }

    public function halfDayEndTimeUsingWhichHalf($which_half)
    {
        return $this->getBusinessHalfDayConfiguration()[$which_half]['end_time'];
    }

    public function halfDayStartEndTime($which_half)
    {
        $half_day_configuration = $this->halfDayStartEnd($which_half);
        $start_time = Carbon::parse($half_day_configuration['start_time'])->format('h:i');
        $end_time = Carbon::parse($half_day_configuration['end_time'])->format('h:i');
        return $start_time . '-' . $end_time;
    }

    public function fullDayStartEndTime()
    {
        $full_day_configuration = $this->officeHour;
        $start_time = Carbon::parse($full_day_configuration->start_time)->format('h:i');
        $end_time = Carbon::parse($full_day_configuration->end_time)->format('h:i');
        return $start_time . '-' . $end_time;
    }

    public function calculationTodayLastCheckInTime($which_half_day)
    {
        if ($which_half_day) {
            if ($which_half_day == HalfDayType::FIRST_HALF) {
                return Carbon::parse($this->halfDayStartTimeUsingWhichHalf(HalfDayType::SECOND_HALF));
            }
            if ($which_half_day == HalfDayType::SECOND_HALF) {
                return Carbon::parse($this->halfDayStartTimeUsingWhichHalf(HalfDayType::FIRST_HALF));
            }
        } else {
            $last_checkin_time = (new TimeByBusiness())->getOfficeStartTimeByBusiness();
            if (is_null($last_checkin_time)) return null;
            return Carbon::parse($last_checkin_time);
        }
    }

    public function calculationTodayLastCheckOutTime($which_half_day)
    {
        if ($which_half_day) {
            if ($which_half_day == HalfDayType::FIRST_HALF) {
                return $this->business->halfDayEndTimeUsingWhichHalf(HalfDayType::SECOND_HALF);
            }
            if ($which_half_day == HalfDayType::SECOND_HALF) {
                return $this->business->halfDayEndTimeUsingWhichHalf(HalfDayType::FIRST_HALF);
            }
        } else {
            $checkout_time = (new TimeByBusiness())->getOfficeEndTimeByBusiness();
            if (is_null($checkout_time)) return null;
            return $checkout_time;
        }
    }

    public function isIpBasedAttendanceEnable()
    {
        if (in_array(AttendanceTypes::IP_BASED, $this->attendanceTypes->pluck('attendance_type')->toArray())) return true;
        return false;
    }
}
