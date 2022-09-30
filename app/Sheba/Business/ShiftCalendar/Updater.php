<?php namespace Sheba\Business\ShiftCalendar;


use Sheba\Business\ShiftSetting\ShiftAssign\Requester;
use Sheba\ModificationFields;

class Updater
{
    use ModificationFields;

    public function update($shift, Requester $request)
    {
        $data = [];

        if ($request->getColorCode() != null) $data['color'] = $request->getColorCode();
        if ($request->getShiftName() != null) $data['shift_name'] = $request->getShiftName();
        if ($request->getShiftTitle() != null) $data['shift_title'] = $request->getShiftTitle();
        if ($request->getStartTime() != null) $data['start_time'] = $request->getStartTime();
        if ($request->getEndTime() != null) $data['end_time'] = $request->getEndTime();
        if ($request->getIsHalfDayActivated() != null) $data['is_half_day'] = $request->getIsHalfDayActivated();

        $shift->update($this->withUpdateModificationField($data));
    }
}
