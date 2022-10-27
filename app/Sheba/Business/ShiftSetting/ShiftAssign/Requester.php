<?php namespace Sheba\Business\ShiftSetting\ShiftAssign;

use Sheba\Helpers\HasErrorCodeAndMessage;

class Requester
{
    use HasErrorCodeAndMessage;

    private $shiftId;
    private $name;
    private $title;
    private $startTime;
    private $endTime;
    private $isHalfDayActivated;
    private $isGeneralActivated;
    private $isUnassignedActivated;
    private $isShiftActivated;
    private $colorCode;
    private $repeat;
    private $repeatType;
    private $repeatRange;
    private $days;
    private $endDate;
    private $calenderData = [];
    private $isCheckinGraceEnable;
    private $isCheckoutGraceEnable;
    private $checkinGraceTime;
    private $checkoutGraceTime;

    public function setShiftId($shiftId)
    {
        $this->shiftId = $shiftId;
        return $this;
    }

    public function getShiftId()
    {
        return $this->shiftId;
    }

    public function setShiftName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function getShiftName()
    {
        return $this->name;
    }

    public function setShiftTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    public function getShiftTitle()
    {
        return $this->title;
    }

    public function setStartTime($start_time)
    {
        $this->startTime = $start_time;
        return $this;
    }

    public function getStartTime()
    {
        return $this->startTime;
    }

    public function setEndTime($end_time)
    {
        $this->endTime = $end_time;
        return $this;
    }

    public function getEndTime()
    {
        return $this->endTime;
    }

    public function setIsHalfDayActivated($is_half_day_activated)
    {
        $this->isHalfDayActivated = $is_half_day_activated;
        return $this;
    }

    public function getIsHalfDayActivated()
    {
        return $this->isHalfDayActivated;
    }

    public function setIsCheckinGraceEnable($is_checkin_grace_enable)
    {
        $this->isCheckinGraceEnable = $is_checkin_grace_enable;
        return $this;
    }

    public function getIsCheckinGraceEnable()
    {
        return $this->isCheckinGraceEnable;
    }

    public function setIsCheckoutGraceEnable($is_checkout_grace_enable)
    {
        $this->isCheckoutGraceEnable = $is_checkout_grace_enable;
        return $this;
    }

    public function getIsCheckoutGraceEnable()
    {
        return $this->isCheckoutGraceEnable;
    }

    public function setCheckinGraceTime($checkin_grace_time)
    {
        $this->checkinGraceTime = $checkin_grace_time;
        return $this;
    }

    public function getCheckinGraceTime()
    {
        return $this->checkinGraceTime;
    }

    public function setCheckoutGraceTime($checkout_grace_time)
    {
        $this->checkoutGraceTime = $checkout_grace_time;
        return $this;
    }

    public function getCheckoutGraceTime()
    {
        return $this->checkoutGraceTime;
    }

    public function setIsGeneralActivated($is_general_activated)
    {
        $this->isGeneralActivated = $is_general_activated;
        return $this;
    }

    public function getIsGeneralActivated()
    {
        return $this->isGeneralActivated;
    }

    public function setIsUnassignedActivated($is_unassigned_activated)
    {
        $this->isUnassignedActivated = $is_unassigned_activated;
        return $this;
    }

    public function getIsUnassignedActivated()
    {
        return $this->isUnassignedActivated;
    }

    public function setIsShiftActivated($is_shift_activated)
    {
        $this->isShiftActivated = $is_shift_activated;
        return $this;
    }

    public function getIsShiftActivated()
    {
        return $this->isShiftActivated;
    }

    public function setRepeat($repeat)
    {
        $this->repeat = $repeat;
        return $this;
    }

    public function getRepeat()
    {
        return $this->repeat;
    }

    public function setRepeatType($repeat_type)
    {
        $this->repeatType = $repeat_type;
        return $this;
    }

    public function getRepeatType()
    {
        return $this->repeatType;
    }

    public function setRepeatRange($repeat_range)
    {
        $this->repeatRange = $repeat_range;
        return $this;
    }

    public function getRepeatRange()
    {
        return $this->repeatRange;
    }

    public function setRepeatDays($days)
    {
        $this->days = $days;
        return $this;
    }

    public function getRepeatDays()
    {
        return $this->days;
    }

    public function setEndDate($end_date)
    {
        $this->endDate = $end_date;
        return $this;
    }

    public function getEndDate()
    {
        return $this->endDate;
    }

    public function setColorCode($color_code)
    {
        $this->colorCode = $color_code;
        return $this;
    }

    public function getColorCode()
    {
        return $this->colorCode;
    }

    public function setData($calender_data)
    {
        $this->calenderData[] = $calender_data;
        return $this;
    }

    public function getData()
    {
        return $this->calenderData;
    }

    public function setShiftAssignError($message)
    {
        $this->setError(400, $message);
    }

    public function getShiftSettings()
    {
        $shift_settings = [
            'repeat'        => $this->repeat,
            'repeat_type'   => $this->repeatType,
            'repeat_range'  => $this->repeatRange,
            'days'          => $this->days,
            'end_date'      => $this->endDate
        ];
        return json_encode($shift_settings);
    }
}
