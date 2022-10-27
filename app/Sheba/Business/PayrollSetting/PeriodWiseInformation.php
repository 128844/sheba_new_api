<?php namespace App\Sheba\Business\PayrollSetting;

use App\Models\BusinessMember;
use App\Sheba\Business\Attendance\AttendanceBasicInfo;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Sheba\Business\Attendance\CheckWeekend;
use Sheba\Dal\Attendance\Model as Attendance;
use Sheba\Dal\AttendanceActionLog\Model as AttendanceActionLog;
use Sheba\Dal\BusinessHoliday\Contract as BusinessHolidayRepoInterface;
use Sheba\Dal\BusinessWeekendSettings\BusinessWeekendSettingsRepo;
use Sheba\Dal\AttendanceActionLog\Contract as AttendanceActionLogRepositoryInterface;
use Sheba\Dal\Attendance\Contract as AttendanceRepositoryInterface;
use Sheba\Dal\ShiftAssignment\ShiftAssignment;
use Sheba\Helpers\TimeFrame;

class PeriodWiseInformation
{
    use AttendanceBasicInfo;

    private $businessHolidayRepo;
    private $businessWeekendRepo;
    private $business;
    private $businessMemberLeave;
    private $isCalculateAttendanceInfo;
    private $result;
    /** @var BusinessMember */
    private $businessMember;
    /** @var AttendanceActionLogRepositoryInterface  */
    private $attendanceLogRepo;
    /** @var AttendanceRepositoryInterface  */
    private $attendanceRepo;
    /** @var CheckWeekend */
    private $weekends;
    /** @var ShiftAssignment[] */
    private $dayWiseShifts = [];

    public function __construct()
    {
        $this->businessHolidayRepo = app(BusinessHolidayRepoInterface::class);
        $this->businessWeekendRepo = app(BusinessWeekendSettingsRepo::class);
        $this->attendanceRepo = app(AttendanceRepositoryInterface::class);
        $this->attendanceLogRepo = app(AttendanceActionLogRepositoryInterface::class);
        $this->result = collect();
        $this->weekends = app(CheckWeekend::class);
    }

    /** @var CarbonPeriod */
    private $period;
    /** @var TimeFrame */
    private $timeFrame;
    private $businessOffice;

    public function setBusinessMember(BusinessMember $business_member)
    {
        $this->businessMember = $business_member;
        return $this;
    }

    public function setPeriod($period)
    {
        $this->period = $period;
        $this->timeFrame = new TimeFrame($this->period->getStartDate(), $this->period->getEndDate());
        return $this;
    }

    public function setBusinessOffice($business_office)
    {
        $this->businessOffice = $business_office;
        $this->business = $this->businessOffice->business;
        return $this;
    }

    public function setBusinessMemberLeave($business_member_leave)
    {
        $this->businessMemberLeave = $business_member_leave;
        return $this;
    }

    public function setIsCalculateAttendanceInfo($is_calculate_attendance_info)
    {
        $this->isCalculateAttendanceInfo = $is_calculate_attendance_info;
        return $this;
    }

    private function initiateResult()
    {
        $this->result->weekend_or_holiday_count = 0;
        $this->result->weekend_count = 0;

        if (!$this->isCalculateAttendanceInfo) return;

        $this->result->total_present = 0;
        $this->result->total_working_days = $this->period->count();
        $this->result->total_late_checkin = 0;
        $this->result->total_early_checkout = 0;
        $this->result->total_late_checkin_or_early_checkout = 0;
        $this->result->grace_time_over = 0;
    }

    public function get()
    {
        $this->initiateResult();
        $this->calculateAttendanceData();
        $this->calculateWorkingDays();
        return $this->result;
    }

    private function calculateAttendanceData()
    {
        if (!$this->isCalculateAttendanceInfo) return;

        $this->getAttendances()->each(function (Attendance $attendance) {
            $attendance->actions->each(function (AttendanceActionLog $action) {
                $this->result->total_late_checkin += $action->isLateCheckIn();
                $this->result->total_early_checkout += $action->isEarlyCheckOut();
                $this->result->grace_time_over += $action->isGraced();
            });
            $this->result->total_present++;
        });
        $this->result->total_late_checkin_or_early_checkout = $this->result->total_late_checkin + $this->result->total_early_checkout;
    }

    private function getAttendances()
    {
        return $this->businessMember->attendances()
            ->select('id', 'business_member_id', 'shift_assignment_id')
            ->with([
                'actions' => function($q) {
                    $q->selectStatusCheck();
                }
            ])
            ->within($this->timeFrame)
            ->notOffDay()
            ->get();
    }

    private function loadShifts()
    {
        $this->dayWiseShifts = $this->businessMember->shifts()
            ->selectBusinessMember()
            ->selectDate()
            ->selectTypes()
            ->within($this->timeFrame)
            ->notGeneral()
            ->get()
            ->toAssocFromKey(function (ShiftAssignment $assignment) {
                return $assignment->getDate()->toDateString();
            });
    }

    private function getShiftOnDate(Carbon $date)
    {
        $key = $date->toDateString();

        if(!array_key_exists($key, $this->dayWiseShifts)) return null;

        return$this->dayWiseShifts[$key];
    }

    private function calculateWorkingDays()
    {
        $this->loadShifts();
        $business_weekend_settings = $this->businessWeekendRepo->getAllByBusiness($this->business);
        $holidays = $this->getHolidays();

        foreach ($this->period as $date) {
            if ($shift = $this->getShiftOnDate($date)) {
                if ($shift->isUnassigned() && $this->isCalculateAttendanceInfo) $this->result->total_working_days--;
                continue;
            }

            $business_weekend = $this->weekends->getWeekendDays($date, $business_weekend_settings);
            $is_holiday = $this->isHoliday($date, $holidays);
            $is_weekend = $this->isWeekend($date, $business_weekend);
            $is_weekend_or_holiday = $is_holiday || $is_weekend;
            $this->result->weekend_or_holiday_count += $is_weekend_or_holiday;
            $this->result->weekend_count += $is_weekend;

            if (!$this->isCalculateAttendanceInfo) continue;

            $is_on_leave = $this->isLeave($date, $this->businessMemberLeave);
            if ($is_weekend_or_holiday || $is_on_leave) {
                $this->result->total_working_days--;
            }
        }
    }

    private function getHolidays()
    {
        $business_holiday = $this->businessHolidayRepo->getAllByBusiness($this->business);
        return $this->getHolidaysFormatted($business_holiday);
    }
}
