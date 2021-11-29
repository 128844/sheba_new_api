<?php namespace Sheba\Business\AttendanceActionLog\ActionChecker;

use Carbon\Carbon;
use Sheba\Business\Leave\HalfDay\HalfDayLeaveCheck;
use Sheba\Dal\AttendanceActionLog\Actions;
use Sheba\Business\AttendanceActionLog\TimeByBusiness;
use Sheba\Business\AttendanceActionLog\WeekendHolidayByBusiness;

class CheckIn extends ActionChecker
{
    public function getActionName()
    {
        return Actions::CHECKIN;
    }

    public function check()
    {
        parent::check(); // TODO: Change the autogenerated stub
        $this->checkForLateAction();
    }

    protected function setAlreadyHasActionForTodayResponse()
    {
        $this->setResult(ActionResultCodes::ALREADY_CHECKED_IN, ActionResultCodeMessages::ALREADY_CHECKED_IN);
    }

    protected function setSuccessfulResponseMessage()
    {
        $this->setResult(ActionResultCodes::SUCCESSFUL, ActionResultCodeMessages::SUCCESSFUL_CHECKIN);
    }

    protected function checkForLateAction()
    {
        $date = Carbon::now();
        $weekendHoliday = new WeekendHolidayByBusiness();

        $which_half_day = (new HalfDayLeaveCheck())->setBusinessMember($this->businessMember)->checkHalfDayLeave();
        $today_last_checkin_time = $this->business->calculationTodayLastCheckInTime($which_half_day);

        if (is_null($today_last_checkin_time)) return;
        if (!$this->isSuccess()) return;

        $today_checkin_time_without_second = Carbon::parse($date->format('Y-m-d H:i'));
        $is_full_day_leave = (new HalfDayLeaveCheck())->setBusinessMember($this->businessMember)->checkFullDayLeave();

        if ($today_checkin_time_without_second->greaterThan($today_last_checkin_time)) {
            if ($weekendHoliday->isWeekendByBusiness($date) || $weekendHoliday->isHolidayByBusiness($date) || $is_full_day_leave) {
                $this->setResult(ActionResultCodes::SUCCESSFUL, ActionResultCodeMessages::SUCCESSFUL_CHECKIN);
            } else {
                $this->setResult(ActionResultCodes::LATE_TODAY, ActionResultCodeMessages::LATE_TODAY);
            }
        }
    }
}
