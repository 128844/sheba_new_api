<?php namespace App\Transformers\Business;

use App\Models\BusinessMember;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class ShiftCalenderTransformer
{
    public function transform(CarbonPeriod $period, $business_members_with_assignments, $total_employee_count)
    {
        return [
            'shift_calender_employee' => $this->transformData($business_members_with_assignments),
            'shift_calender_header' => $this->transformDates($period),
            'total_employees' => $total_employee_count
        ];
    }

    private function transformData($business_members_with_assignments)
    {
        $data = [];
        foreach ($business_members_with_assignments as $business_member) {
            /** @var BusinessMember $business_member */

            $department = $business_member->department();
            $profile = $business_member->member->profile;

            $data[$business_member->id]['employee'] = [
                'business_member_id' => $business_member->id,
                'employee_id' => $business_member->employee_id,
                'name' => $profile->name,
                'department_name' => $department->name,
                'pro_pic' => $profile->pro_pic,
            ];

            $assignments = [];

            foreach ($business_member->shifts as $shift) {
                $assignments[] = $shift ? [
                    'id' => $shift->id,
                    'date' => $shift->date,
                    'business_member_id' => $shift->business_member_id,
                    'is_general' => $shift->is_general,
                    'is_unassigned' => $shift->is_unassigned,
                    'is_shift' => $shift->is_shift,
                    'shift_name' => $shift->shift_name,
                    'shift_title' => $shift->shift_title,
                    'shift_color' => $shift->color_code,
                    'shift_start' => Carbon::parse($shift->start_time)->format('h:i A'),
                    'shift_end' => Carbon::parse($shift->end_time)->format('h:i A'),
                ] : null;
            }

            $data[$business_member->id]['date'] = $assignments;

//            if (!isset($data[$shift_calender->business_member_id]['display_priority'])) $data[$shift_calender->business_member_id]['display_priority'] = $shift_calender->is_shift == 1 ? 0 : 1;
//            else if ($shift_calender->is_shift) $data[$shift_calender->business_member_id]['display_priority'] = $data[$shift_calender->business_member_id]['display_priority'] == 0 ? 0 : 1;
        }
        return $data;
    }

    private function transformDates(CarbonPeriod $period)
    {
        return array_map(function (Carbon $date) {
            return [
                'date_raw' => $date->toDateString(),
                'date' => $date->format('d M'),
                'day' => $date->format('D')
            ];
        }, $period->toArray());
    }
}
