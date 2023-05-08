<?php namespace App\Transformers\Business;

use Carbon\Carbon;
use League\Fractal\TransformerAbstract;
use Sheba\Dal\ShiftAssignment\ShiftAssignment;

class EmployeeShiftDetailsTransformer extends TransformerAbstract
{
    public function transform(ShiftAssignment $shift_assignment)
    {
        $shift_details = [
            'id'                        => $shift_assignment->id,
            'business_member_id'        => $shift_assignment->business_member_id,
            'shift_id'                  => $shift_assignment->shift_id,
            'shift_name'                => $shift_assignment->shift_name,
            'date'                      => Carbon::parse($shift_assignment->date)->toDateString(),
            'start_time'                => Carbon::parse($shift_assignment->start_time)->format('h:i A'),
            'end_time'                  => Carbon::parse($shift_assignment->end_time)->format('h:i A'),
            'is_half_day'               => $shift_assignment->is_half_day,
            'color_code'                => $shift_assignment->color_code,
            'is_general'                => $shift_assignment->is_general,
            'is_unassigned'             => $shift_assignment->is_unassigned,
            'is_shift'                  => $shift_assignment->is_shift,
            'shift_settings'            => $shift_assignment->shift_settings,
            'created_by'                => $shift_assignment->created_by,
            'created_by_name'           => $shift_assignment->created_by_name,
            'updated_by'                => $shift_assignment->updated_by,
            'updated_by_name'           => $shift_assignment->updated_by_name
        ];
        if($shift_assignment->is_shift) $shift_details['title'] = $shift_assignment->shift_title;
        return $shift_details;
    }
}
