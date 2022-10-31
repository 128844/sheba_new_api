<?php namespace Sheba\Business\AttendanceActionLog\StatusCalculator;

use Sheba\Dal\Attendance\Statuses;
use Carbon\Carbon;

class ShiftCheckoutStatusCalculator extends ShiftStatusCalculator
{
    public function calculate()
    {
        $checkout_time = $this->businessMember->calculationTodayLastCheckOutTime($this->whichHalfDay, $this->shiftAssignment);
        if (Carbon::now()->toTimeString() >= $checkout_time) return Statuses::LEFT_TIMELY;
        return Statuses::LEFT_EARLY;
    }
}
