<?php namespace Sheba\Business\AttendanceActionLog\ActionChecker;

use Sheba\Business\Attendance\AttendanceTypes\AttendanceSuccess;
use Sheba\Business\Attendance\AttendanceTypes\TypeFactory;
use Sheba\Business\AttendanceActionLog\TimeByBusiness;
use Sheba\Business\AttendanceActionLog\WeekendHolidayByBusiness;
use Sheba\Business\Leave\HalfDay\HalfDayLeaveCheck;
use Sheba\Dal\Attendance\Statuses;
use Sheba\Dal\AttendanceActionLog\Model as AttendanceActionLog;
use Sheba\Dal\Attendance\Model as Attendance;
use App\Models\BusinessMember;
use App\Models\Business;
use Sheba\Dal\ShiftAssignment\ShiftAssignment;
use Sheba\Location\Coords;
use Sheba\Location\Geo;
use Carbon\Carbon;

abstract class ActionChecker
{
    /** @var Geo */
    protected $geo;
    /** @var Attendance */
    protected $attendanceOfToday;
    /** @var ShiftAssignment */
    protected $shiftAssignment;
    /** @var AttendanceActionLog[] */
    protected $attendanceLogsOfToday;
    /** @var Business $business */
    protected $business;
    /** @var BusinessMember $businessMember */
    protected $businessMember;
    protected $ip;
    protected $deviceId;
    protected $resultCode;
    protected $resultMessage;
    protected $isRemote = 0;
    const BUSINESS_OFFICE_HOUR = 1;
    /** @var AttendanceSuccess  */
    protected $attendanceSuccess = null;
    private $geoOffices;
    private $lat;
    private $lng;
    /** @var ActionResult */
    protected $result;

    /**
     * @param Business $business
     * @return ActionChecker
     */
    public function setBusiness($business)
    {
        $this->business = $business;
        $this->geoOffices = $this->business->geoOffices()->get();
        return $this;
    }

    /**
     * @param $business_member
     * @return $this
     */
    public function setBusinessMember($business_member)
    {
        $this->businessMember = $business_member;
        return $this;
    }

    /**
     * @param Geo $geo
     * @return $this
     */
    public function setGeo(Geo $geo)
    {
        $this->geo = $geo;
        return $this;
    }

    public function setAttendanceOfToday($attendance)
    {
        $this->attendanceOfToday = $attendance;
        return $this;
    }

    public function setShiftAssignment($assignment)
    {
        $this->shiftAssignment = $assignment;
        return $this;
    }

    public function setIp($ip)
    {
        $this->ip = $ip;
        return $this;
    }

    public function setDeviceId($device_id)
    {
        $this->deviceId = $device_id;
        return $this;
    }

    public function setLat($lat)
    {
        $this->lat = $lat;
        return $this;
    }

    public function setLng($lng)
    {
        $this->lng = $lng;
        return $this;
    }

    /**
     * @return ActionResult
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @return AttendanceSuccess
     */
    public function getAttendanceSuccess()
    {
        return $this->attendanceSuccess;
    }

    public function check()
    {
        $this->setAttendanceActionLogsOfToday();
        $this->checkAlreadyHasActionForToday();
        $this->checkDeviceId();
        $this->checkAttendanceType();
        if (!$this->isAlreadyFailed()) $this->setResult(ActionResult::SUCCESSFUL);
    }

    private function setAttendanceActionLogsOfToday()
    {
        if ($this->attendanceOfToday) $this->attendanceLogsOfToday = $this->attendanceOfToday->actions;
    }

    protected function checkAlreadyHasActionForToday()
    {
        if ($this->isAlreadyFailed()) return;
        if ($this->hasSameActionForToday()) $this->setAlreadyHasActionForTodayResponse();
    }

    private function hasSameActionForToday()
    {
        return $this->attendanceLogsOfToday && $this->attendanceLogsOfToday->filter(function ($log) {
                return $log->action == $this->getActionName();
            })->count() > 0;
    }

    protected function checkDeviceId()
    {
        if ($this->isAlreadyFailed()) return;
        if ($this->hasDifferentDeviceId()) {
            $this->setResult(ActionResult::DEVICE_UNAUTHORIZED);
        } elseif ($this->hasDeviceUsedInDifferentAccountToday()) {
            $this->setResult(ActionResult::ALREADY_DEVICE_USED);
        }
    }

    private function hasDifferentDeviceId()
    {
        return $this->attendanceLogsOfToday && $this->attendanceLogsOfToday->filter(function ($log) {
                return $log->device_id != $this->deviceId;
            })->count() > 0;
    }

    private function hasDeviceUsedInDifferentAccountToday()
    {
        $business_members = $this->business->members()->select('business_member.id');
        if ($this->attendanceOfToday) $business_members = $business_members->where('business_member.id', '<>', $this->attendanceOfToday->business_member_id);
        $business_members = $business_members->get();
        if (count($business_members) == 0) return 0;
        $attendances_count = Attendance::whereIn('business_member_id', $business_members->pluck('id'))->where('date', date('Y-m-d'))->whereHas('actions', function ($q) {
            $q->where('device_id', $this->deviceId);
        })->select('id')->count();
        return $attendances_count > 0 ? 1 : 0;
    }

    protected function checkAttendanceType()
    {
        if ($this->isAlreadyFailed()) return;
        $coords = new Coords(floatval($this->lat), floatval($this->lng));
        $checker = TypeFactory::create($this->businessMember, $this->ip, $coords);
        $checker_success = $checker->check();
        $error = $checker->getErrors()->getSingle();

        if ($checker_success) $this->attendanceSuccess = $checker_success;
        else if ($error) $this->setResult($error);
        else throw new \Exception('No error or success found.');
    }

    protected function setResult($result_code)
    {
        $this->result = new ActionResult($result_code);
    }

    protected function isAlreadyFailed()
    {
        return $this->result ? $this->result->isFailed() : false;
    }

    public function isLateNoteRequired()
    {
        $date = Carbon::now();
        $time = new TimeByBusiness();
        $weekendHoliday = new WeekendHolidayByBusiness();
        $checkin_time = $time->getOfficeStartTimeByBusiness();

        if (is_null($checkin_time)) return 0;
        if (!$weekendHoliday->isWeekendByBusiness($date) && !$weekendHoliday->isHolidayByBusiness($date)) {
            return Carbon::now()->gt(Carbon::parse($checkin_time)) ? 1 : 0;
        }
        return 0;
    }

    public function isLeftEarlyNoteRequired()
    {
        $date = Carbon::now();
        $time = new TimeByBusiness();
        $weekendHoliday = new WeekendHolidayByBusiness();
        $checkout_time = $time->getOfficeEndTimeByBusiness();

        if (is_null($checkout_time)) return 0;
        if (!$weekendHoliday->isWeekendByBusiness($date) && !$weekendHoliday->isHolidayByBusiness($date)) {
            return Carbon::now()->lt(Carbon::parse($checkout_time)) ? 1 : 0;
        }
        return 0;
    }

    public function isLateNoteRequiredForSpecificDate(Attendance $last_attendance)
    {
        $checkin_action = $last_attendance->checkinAction();
        if (!$last_attendance->is_off_day) return $checkin_action->status == Statuses::LATE;
        return 0;
    }

    public function isLeftEarlyNoteRequiredForSpecificDate(Attendance $last_attendance)
    {
        $checkin_action = $last_attendance->checkoutAction();
        if (!$last_attendance->is_off_day) return $checkin_action->status == Statuses::LATE;
        return 0;
    }

    protected function isNotInShift(): bool
    {
        return !$this->shiftAssignment || !$this->shiftAssignment->isInShift();
    }

    protected function getHalfDay()
    {
        return (new HalfDayLeaveCheck())->setBusinessMember($this->businessMember)->checkHalfDayLeave();
    }

    abstract protected function setAlreadyHasActionForTodayResponse();

    abstract protected function getActionName();
}
