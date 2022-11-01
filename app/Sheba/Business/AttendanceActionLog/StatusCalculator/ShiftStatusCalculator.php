<?php namespace Sheba\Business\AttendanceActionLog\StatusCalculator;

use App\Models\BusinessMember;
use Sheba\Dal\Attendance\Model as Attendance;

abstract class ShiftStatusCalculator
{
    /** @var Attendance $attendance */
    protected $attendance;
    protected $action;
    protected $whichHalfDay;
    protected $checkinTime;
    protected $checkoutTime;
    protected $newCheckinTime;
    protected $newCheckoutTime;
    /*** @var BusinessMember */
    protected $businessMember;
    protected $shiftAssignment;


    public function setBusinessMember(BusinessMember $business_member)
    {
        $this->businessMember = $business_member;
        return $this;
    }

    public function setShiftAssignment($shift_assignment)
    {
        $this->shiftAssignment = $shift_assignment;
        return $this;
    }
    /**
     * @param Attendance $attendance
     * @return $this
     */
    public function setAttendance(Attendance $attendance)
    {
        $this->attendance = $attendance;
        return $this;
    }

    public function setAction($action)
    {
        $this->action = $action;
        return $this;
    }
    public function setWhichHalfDay($which_half)
    {
        $this->whichHalfDay = $which_half;
        return $this;
    }

    public function setCheckinTime($checkin_time)
    {
        $this->checkinTime = $checkin_time;
        return $this;
    }


    public function setNewCheckInTime($new_checkin_time)
    {
        $this->newCheckinTime = $new_checkin_time;
        return $this;
    }

    public function setNewCheckOutTime($new_checkout_time)
    {
        $this->newCheckoutTime = $new_checkout_time;
        return $this;
    }

    public function setCheckoutTime($checkout_time)
    {
        $this->checkoutTime = $checkout_time;
        return $this;
    }

    abstract public function calculate();

}
