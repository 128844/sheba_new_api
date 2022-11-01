<?php namespace Sheba\Business\ShiftCalendar;


use Sheba\Business\ShiftSetting\ShiftAssign\Requester;
use Sheba\ModificationFields;

class Updater
{
    use ModificationFields;

    public function update($shift, Requester $request)
    {
        if ($request->getColorCode()) {
            $data['color_code'] = $request->getColorCode();
            return $shift->update($this->withUpdateModificationField($data));
        }

        $data = [
            'shift_name' => $request->getShiftName(),
            'shift_title' => $request->getShiftTitle(),
            'start_time' => $request->getStartTime(),
            'end_time' => $request->getEndTime(),
            'is_half_day' => $request->getIsHalfDayActivated(),
            'checkin_grace_enable' => $request->getIsCheckinGraceEnable(),
            'checkout_grace_enable' => $request->getIsCheckoutGraceEnable(),
            'checkin_grace_time' => $request->getCheckinGraceTime(),
            'checkout_grace_time' => $request->getCheckoutGraceTime()
        ];

        if ($request->getIsGeneralActivated()) $data['is_general'] = $request->getIsGeneralActivated();
        if ($request->getIsShiftActivated()) $data['is_shift'] = $request->getIsShiftActivated();
        if ($request->getIsUnassignedActivated()) $data['is_unassigned'] = $request->getIsUnassignedActivated();

        return $shift->update($this->withUpdateModificationField($data));
    }
}
