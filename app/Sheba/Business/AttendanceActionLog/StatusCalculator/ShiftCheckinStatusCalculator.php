<?php namespace Sheba\Business\AttendanceActionLog\StatusCalculator;

use Sheba\Dal\Attendance\Statuses;
use Carbon\Carbon;

class ShiftCheckinStatusCalculator extends ShiftStatusCalculator
{
    public function calculate(): string
    {
        $today_last_checkin_time = $this->businessMember->calculationTodayLastCheckInTime($this->whichHalfDay, $this->shiftAssignment);
        if ($today_last_checkin_time >= Carbon::now()->toTimeString()) return Statuses::ON_TIME;
        return Statuses::LATE;
    }
}
