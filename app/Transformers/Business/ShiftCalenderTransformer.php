<?php namespace App\Transformers\Business;

use App\Models\BusinessMember;
use Carbon\Carbon;

class ShiftCalenderTransformer
{
    public function transform($shift_calenders)
    {
        $data = $header = [];
        foreach ($shift_calenders as $shift_calender)
        {
            if (count($header) < 7) {
                $header[] =
                    [
                        'date_raw' => $shift_calender->date,
                        'date' => Carbon::parse($shift_calender->date)->format('d M'),
                        'day' => Carbon::parse($shift_calender->date)->format('D')
                    ];
            }
            $business_member = null;
            if (!isset($data[$shift_calender->business_member_id]['employee'])){
                $business_member = BusinessMember::find($shift_calender->business_member_id);
                $department = $business_member->department();
                $profile = $business_member->member->profile;
                $data[$shift_calender->business_member_id]['employee'] = [
                    'business_member_id' => $shift_calender->business_member_id,
                    'employee_id' => $business_member->employee_id,
                    'name' => $profile->name,
                    'department_name' => $department->name,
                    'pro_pic' => $profile->pro_pic
                ];
            }

            $data[$shift_calender->business_member_id]['date'][] = [
                'id' => $shift_calender->id,
                'date' => $shift_calender->date,
                'business_member_id' => $shift_calender->business_member_id,
                'is_general' => $shift_calender->is_general,
                'is_unassigned' => $shift_calender->is_unassigned,
                'is_shift' => $shift_calender->is_shift,
                'shift_name' => $shift_calender->shift_name,
                'shift_title' => $shift_calender->shift_title,
                'shift_color' => $shift_calender->color_code,
                'shift_start' => Carbon::parse($shift_calender->start_time)->format('h:i A'),
                'shift_end' => Carbon::parse($shift_calender->end_time)->format('h:i A'),
            ];
            if (!isset($data[$shift_calender->business_member_id]['display_priority'])) $data[$shift_calender->business_member_id]['display_priority'] = $shift_calender->is_shift == 1 ? 0 : 1;
            else
                if ($shift_calender->is_shift) $data[$shift_calender->business_member_id]['display_priority'] = $data[$shift_calender->business_member_id]['display_priority'] == 0 ? 0 : 1;
        }
        return ['header' => $header, 'data' => $data];
    }
}
