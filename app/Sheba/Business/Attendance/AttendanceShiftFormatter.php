<?php namespace Sheba\Business\Attendance;

use Sheba\Dal\Attendance\Model as Attendance;

class AttendanceShiftFormatter
{
    /**
     * @param Attendance $attendance
     * @return array
     */
    public static function get(Attendance $attendance)
    {
        $shift_assignment = $attendance->shiftAssignment;
        if (!$shift_assignment) return [
            'is_general' => 1,
            'is_unassigned' => 0,
            'details' => null
        ];

        return [
            'is_general' => $shift_assignment->is_general,
            'is_unassigned' => $shift_assignment->is_unassigned,
            'details' => $shift_assignment->is_shift ? [
                'id' => $shift_assignment->shift_id,
                'name' => $shift_assignment->shift_title,
                'color' => $shift_assignment->color_code,
            ] : null
        ];
    }
}
