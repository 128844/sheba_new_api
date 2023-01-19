<?php

namespace App\Models;

use App\Sheba\Business\Attendance\HalfDaySetting\HalfDayType;
use Jenssegers\Mongodb\Eloquent\HybridRelations;
use Sheba\Business\AttendanceActionLog\TimeByBusiness;
use Sheba\Business\BusinessMember\ProfileAndDepartmentQuery;
use Sheba\Business\CoWorker\Statuses;
use Sheba\Dal\Appreciation\Appreciation;
use Sheba\Dal\BusinessMemberBkashInfo\BusinessMemberBkashInfo;
use Sheba\Dal\BusinessMemberStatusChangeLog\Model as BusinessMemberStatusChangeLog;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Sheba\Dal\Attendance\Model as Attendance;
use Sheba\Dal\BusinessHoliday\Contract as BusinessHolidayRepoInterface;
use Sheba\Dal\BusinessWeekend\Contract as BusinessWeekendRepoInterface;
use Sheba\Dal\Leave\Model as Leave;
use Sheba\Dal\BusinessMemberLeaveType\Model as BusinessMemberLeaveType;
use Sheba\Dal\Salary\Salary;
use Sheba\Dal\ShiftAssignment\ShiftAssignment;
use Sheba\Dal\TrackingLocation\TrackingLocation;
use Sheba\Helpers\TimeFrame;
use Sheba\Business\BusinessMember\Events\BusinessMemberCreated;
use Sheba\Business\BusinessMember\Events\BusinessMemberUpdated;
use Sheba\Business\BusinessMember\Events\BusinessMemberDeleted;

class BusinessMember extends Model
{
    use SoftDeletes;
    use HybridRelations;

    protected $guarded = ['id',];
    protected $dates = ['join_date', 'deleted_at'];
    protected $casts = ['is_super' => 'int'];

    protected $dispatchesEvents = [
        'created' => BusinessMemberCreated::class,
        'updated' => BusinessMemberUpdated::class,
        'deleted' => BusinessMemberDeleted::class,
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $table = config('database.connections.mysql.database') . '.business_member';
        $this->setTable($table);
    }

    public function setTable($table)
    {
        $this->table = $table;
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function actions()
    {
        return $this->belongsToMany(Action::class);
    }

    public function role()
    {
        return $this->belongsTo(BusinessRole::class, 'business_role_id');
    }

    public function salary()
    {
        return $this->hasOne(Salary::class);
    }

    public function bkashInfos()
    {
        return $this->hasMany(BusinessMemberBkashInfo::class);
    }

    public function department()
    {
        return $this->role ? $this->role->businessDepartment : null;
    }

    public function isSuperAdmin()
    {
        return $this->is_super;
    }

    public function isWithBusiness(Business $business)
    {
        return $this->business_id == $business->id;
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function attendanceOfToday()
    {
        return $this->hasMany(Attendance::class)->where('date', (Carbon::now())->toDateString())->first();
    }

    public function attendanceOfYesterday()
    {
        return $this->hasMany(Attendance::class)->where('date', (Carbon::now())->subDay()->toDateString())->first();
    }

    public function lastAttendance()
    {
        return $this->hasMany(Attendance::class)->orderBy('id', 'desc')->first();
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }

    public function manager()
    {
        return $this->belongsTo(BusinessMember::class, 'manager_id');
    }

    public function isManager(): bool
    {
        return $this->business->getActiveBusinessMember()->where('manager_id', $this->id)->count() > 0;
    }

    public function trackingLocations()
    {
        return $this->hasMany(TrackingLocation::class);
    }

    public function lastLiveLocation()
    {
        return $this->hasMany(TrackingLocation::class)->orderBy('created_at', 'desc')->first();
    }

    public function statusChangeLogs()
    {
        return $this->hasMany(BusinessMemberStatusChangeLog::class);
    }

    public function appreciations()
    {
        return $this->hasMany(Appreciation::class, 'receiver_id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active', 'invited']);
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOnlyActive($query)
    {
        return $query->status(Statuses::ACTIVE);
    }

    public function scopeAccessible($query)
    {
        return $query->notStatus(Statuses::INACTIVE);
    }

    public function scopeNotStatus($query, $status)
    {
        return $query->where('status', '<>', $status);
    }

    public function scopeNotInvited($query)
    {
        return $query->notStatus(Statuses::INVITED);
    }

    public function scopeWithProfileAndDepartment($query, ProfileAndDepartmentQuery $request = null)
    {
        $request = $request ?? new ProfileAndDepartmentQuery();

        if (!empty($request->department)) {
            $query->whereHas('role', function ($rq) use ($request) {
                $rq->whereHas('businessDepartment', function ($bdq) use ($request) {
                    $bdq->where('business_departments.id', $request->department);
                });
            });
        }

        if (!empty($request->searchTerm)) {
            $query->whereHas('member.profile', function ($pq) use ($request) {
                $pq->where('name', 'LIKE', "%$request->searchTerm%");
            })->orWhere('employee_id', 'LIKE', "%$request->searchTerm%");
        }

        $query->with([
            'member' => function ($mq) use ($request) {
                $mq
                    ->select('members.id', 'profile_id')
                    ->with([
                        'profile' => function ($pq) use ($request) {
                            $pq->select('profiles.id');
                            foreach ($request->profileColumns as $profile_column) {
                                $pq->addSelect($profile_column);
                            }
                        }
                    ]);
            }, 'role' => function ($rq) {
                $rq
                    ->select('business_roles.id', 'business_department_id', 'name')
                    ->with([
                        'businessDepartment' => function ($bdq) {
                            $bdq->select('business_departments.id', 'business_id', 'name');
                        }
                    ]);
            }
        ]);

        return $query;
    }

    /**
     * @param Carbon $date
     * @return bool
     */
    public function isOnLeaves(Carbon $date)
    {
        $date = $date->toDateString();
        $leave = $this->leaves()->accepted()->whereRaw("('$date' BETWEEN start_date AND end_date)")->first();
        return !!$leave;
    }

    /**
     * @param $leave_type_id
     * @return int
     */
    public function getCountOfUsedLeaveDaysByTypeOnAFiscalYear($leave_type_id)
    {
        $time_frame = $this->getBusinessFiscalPeriod();

        $leaves = $this->leaves()->accepted()->between($time_frame)->with('leaveType')->whereHas('leaveType', function ($leave_type) use ($leave_type_id) {
            return $leave_type->where('id', $leave_type_id);
        })->get();

        $business_holiday = app(BusinessHolidayRepoInterface::class)->getAllDateArrayByBusiness($this->business);
        $business_weekend = app(BusinessWeekendRepoInterface::class)->getAllByBusiness($this->business)->pluck('weekday_name')->toArray();

        return $this->getCountOfUsedDays($leaves, $time_frame, $business_holiday, $business_weekend);
    }

    /**
     * @param Collection $leaves
     * @param array $business_holiday
     * @param array $business_weekend
     * @return int
     */
    public function getCountOfUsedLeaveDaysByFiscalYear(Collection $leaves, array $business_holiday, array $business_weekend)
    {
        $time_frame = $this->getBusinessFiscalPeriod();
        return $this->getCountOfUsedDays($leaves, $time_frame, $business_holiday, $business_weekend);
    }

    /**
     * @param Collection $leaves
     * @param $time_frame
     * @param array $business_holiday
     * @param array $business_weekend
     * @return float
     */
    public function getCountOfUsedLeaveDaysByDateRange(Collection $leaves, $time_frame, array $business_holiday, array $business_weekend)
    {
        return $this->getCountOfUsedDays($leaves, $time_frame, $business_holiday, $business_weekend);
    }

    public function getBusinessFiscalPeriod()
    {
        $business_fiscal_start_month = $this->business->fiscal_year ?: Business::BUSINESS_FISCAL_START_MONTH;
        $time_frame = new TimeFrame();
        return $time_frame->forAFiscalYear(Carbon::now(), $business_fiscal_start_month);
    }

    private function getCountOfUsedDays(Collection $leaves, $time_frame, array $business_holiday, array $business_weekend)
    {
        $used_days = 0;
        $leave_day_into_holiday_or_weekend = 0;

        $leaves->each(function ($leave) use (&$used_days, $time_frame, $business_weekend, $business_holiday, $leave_day_into_holiday_or_weekend) {
            if (!$this->isLeaveInCurrentFiscalYear($time_frame, $leave)) return;
            if ($this->isLeaveFullyInAFiscalYear($time_frame, $leave)) {
                $used_days += $leave->total_days;
                return;
            }

            $start_date = $leave->start_date->lt($time_frame->start) ? $time_frame->start : $leave->start_date;
            $end_date = $leave->end_date->gt($time_frame->end) ? $time_frame->end : $leave->end_date;

            if (!$this->business->is_sandwich_leave_enable) {
                $period = CarbonPeriod::create($start_date, $end_date);
                foreach ($period as $date) {
                    $day_name_in_lower_case = strtolower($date->format('l'));
                    if (in_array($day_name_in_lower_case, $business_weekend)) {
                        $leave_day_into_holiday_or_weekend++;
                        continue;
                    }
                    if (in_array($date->toDateString(), $business_holiday)) {
                        $leave_day_into_holiday_or_weekend++;
                        continue;
                    }
                }
            }

            $used_days += ($end_date->diffInDays($start_date) + 1) - $leave_day_into_holiday_or_weekend;
        });

        return (float)$used_days;
    }

    private function isLeaveFullyInAFiscalYear($fiscal_year_time_frame, Leave $leave)
    {
        return $leave->start_date->between($fiscal_year_time_frame->start, $fiscal_year_time_frame->end) &&
            $leave->end_date->between($fiscal_year_time_frame->start, $fiscal_year_time_frame->end);
    }

    private function isLeaveInCurrentFiscalYear($fiscal_year_time_frame, Leave $leave)
    {
        return $leave->start_date->between($fiscal_year_time_frame->start, $fiscal_year_time_frame->end) &&
            $leave->end_date->between($fiscal_year_time_frame->start, $fiscal_year_time_frame->end);
    }

    public function leaveTypes()
    {
        return $this->hasMany(BusinessMemberLeaveType::class);
    }

    /**
     * @param $leave_type_id
     * @return mixed
     */
    public function getTotalLeaveDaysByLeaveTypes($leave_type_id)
    {
        return $this->getBusinessMemberLeaveType($leave_type_id)->total_days;
    }

    public function getBusinessMemberLeaveType($leave_type_id)
    {
        $business_member_leave_type = $this->leaveTypes()->where('leave_type_id', $leave_type_id)->first();
        if ($business_member_leave_type) return $business_member_leave_type;
        return $this->business->leaveTypes()->withTrashed()->where('id', $leave_type_id)->first();
    }

    /**
     * @param Carbon $date
     * @return bool
     */
    public function getLeaveOnASpecificDate(Carbon $date)
    {
        $date = $date->toDateString();
        return $this->leaves()->accepted()->whereRaw("('$date' BETWEEN start_date AND end_date)")->first();
    }

    public function getCurrentFiscalYearLeaves()
    {
        $time_frame = $this->getBusinessFiscalPeriod();

        $leaves = $this->leaves()->between($time_frame)->with('leaveType')->whereHas('leaveType', function ($leave_type) {
            return $leave_type->withTrashed();
        })->get();
        return $leaves;
    }

    public function profile()
    {
        return DB::table('business_member')
            ->join('members', 'members.id', '=', 'business_member.member_id')
            ->join('profiles', 'profiles.id', '=', 'members.profile_id')
            ->where('business_member.id', '=', $this->id)
            ->first();
    }

    public function isNewJoiner()
    {
        $start_date = $this->join_date;
        if (!$start_date) return false;
        $end_date = $this->join_date->addDays(30);
        $time_frame = new TimeFrame();
        $time_frame->forDateRange($start_date, $end_date);
        return $time_frame->hasDateBetween(Carbon::now());
    }

    public function isBusinessMemberActive()
    {
        return $this->status == Statuses::ACTIVE;
    }

    /**
     * @param $date
     * @return mixed
     */
    public function liveLocationFilterByDate($date = null)
    {
        $tracking_locations = $this->trackingLocations()->orderBy('created_at', 'desc');
        if ($date) return $tracking_locations->where('date', $date);
        return $tracking_locations;
    }

    public function liveLocationForADateRange($from_date, $to_date)
    {
        $tracking_locations = $this->trackingLocations()->where(function ($query) use ($from_date, $to_date) {
            $query->where('date', '>=', $from_date);
            $query->where('date', '<=', $to_date);
        });
        return $tracking_locations->orderBy('created_at', 'desc');
    }

    public function isShiftEnable()
    {
        return $this->is_shift_enable;
    }

    public function shifts()
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function generalShift()
    {
        return $this->shifts()->where('is_general', 1);
    }

    public function calculationTodayLastCheckInTime($which_half, $shift_assignment): string
    {
        if ($which_half == HalfDayType::FIRST_HALF) {
            $time_diff = Carbon::parse($shift_assignment->start_time)->diffInHours($shift_assignment->end_time);
            # If A Employee Has Leave On First_Half, Office Start Time Will Be Second_Half Start_Time
            $last_checkin_time = Carbon::parse($shift_assignment->start_time)->addHours($time_diff / 2);
            if ($shift_assignment->checkin_grace_enable) return $last_checkin_time->addMinutes($shift_assignment->checkin_grace_time)->toTimeString();
            return $last_checkin_time->toTimeString();
        } else {
            $last_checkin_time = Carbon::parse($shift_assignment->start_time);
            if ($shift_assignment->checkin_grace_enable) return $last_checkin_time->addMinutes($shift_assignment->checkin_grace_time)->toTimeString();
            return $last_checkin_time->toTimeString();
        }
    }

    public function calculationTodayLastCheckOutTime($which_half_day, $shift_assignment): string
    {
        if ($which_half_day == HalfDayType::SECOND_HALF) {
            $time_diff = Carbon::parse($shift_assignment->start_time)->diffInHours($shift_assignment->end_time);
            $checkout_time = Carbon::parse($shift_assignment->start_time)->addHours($time_diff / 2);
            if ($shift_assignment->checkout_grace_enable) return $checkout_time->subMinutes($shift_assignment->checkout_grace_time)->toTimeString();
            return $checkout_time->toTimeString();
        } else {
            $checkout_time = Carbon::parse($shift_assignment->end_time);
            if ($shift_assignment->checkout_grace_enable) return $checkout_time->subMinutes($shift_assignment->checkout_grace_time)->toTimeString();
            return $checkout_time->toTimeString();
        }
    }

    public function shiftAssignmentFromYesterdayToTomorrow()
    {
        return $this->shift()->whereBetween('date', [Carbon::now()->subDay()->toDateString(), Carbon::now()->addDay()->toDateString()])->get();
    }
}
