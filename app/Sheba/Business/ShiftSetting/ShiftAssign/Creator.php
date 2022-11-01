<?php namespace Sheba\Business\ShiftSetting\ShiftAssign;

use Sheba\Dal\ShiftAssignment\ShiftAssignmentRepository;

class Creator
{
    /*** @var ShiftAssignmentRepository  $shiftAssignmentRepository*/
    private $shiftAssignmentRepository;
    /** @var Requester $shiftCalenderRequester */
    private $shiftCalenderRequester;

    public function __construct(ShiftAssignmentRepository $shift_assignment_repository)
    {
        $this->shiftAssignmentRepository = $shift_assignment_repository;
    }

    public function setShiftCalenderRequester(Requester $shift_calender_requester)
    {
        $this->shiftCalenderRequester = $shift_calender_requester;
        return $this;
    }

    public function update($shift_calender)
    {
        $data = $this->makeData();
        foreach ($shift_calender as $calender_data)
        {
            $this->shiftAssignmentRepository->update($calender_data, $data);
        }
    }

    private function makeData()
    {
        return [
            'shift_id' => $this->shiftCalenderRequester->getShiftId(),
            'shift_name' => $this->shiftCalenderRequester->getShiftName(),
            'shift_title' => $this->shiftCalenderRequester->getShiftTitle(),
            'start_time' => $this->shiftCalenderRequester->getStartTime(),
            'end_time' => $this->shiftCalenderRequester->getEndTime(),
            'is_half_day' => $this->shiftCalenderRequester->getIsHalfDayActivated(),
            'checkin_grace_enable' => $this->shiftCalenderRequester->getIsCheckinGraceEnable(),
            'checkin_grace_time'    => $this->shiftCalenderRequester->getCheckinGraceTime(),
            'checkout_grace_enable' => $this->shiftCalenderRequester->getIsCheckoutGraceEnable(),
            'checkout_grace_time'   => $this->shiftCalenderRequester->getCheckoutGraceTime(),
            'is_general' => $this->shiftCalenderRequester->getIsGeneralActivated(),
            'shift_settings' => $this->shiftCalenderRequester->getShiftSettings(),
            'is_unassigned' => $this->shiftCalenderRequester->getIsUnassignedActivated(),
            'is_shift' => $this->shiftCalenderRequester->getIsShiftActivated(),
            'color_code' => $this->shiftCalenderRequester->getColorCode()
        ];
    }
}
