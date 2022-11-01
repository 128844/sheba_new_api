<?php namespace App\Sheba\Employee;

use Carbon\Carbon;

class ShiftCalender
{
    private $shift_calender;
    private $business;
    private $employeeAttendances;

    public function __construct($business, $shift_calender, $employee_attendances)
    {
        $this->business = $business;
        $this->shift_calender = $shift_calender;
        $this->employeeAttendances = $employee_attendances;
    }

    public function employee_shift_calender()
    {
        $shifts = $employee_shift_calender = [];
        $business_start_time = Carbon::parse($this->business->officeHour->start_time)->format('h:i A');
        $business_end_time = Carbon::parse($this->business->officeHour->end_time)->format('h:i A');
        foreach($this->shift_calender as $employeeShift)
        {
            $has_checkin = array_key_exists($employeeShift->date, $this->employeeAttendances) ? 1 : 0;
            $temp_calender =  [
                'id' => $employeeShift->id,
                'date' => $employeeShift->date,
                'has_checkin' => $has_checkin,
                'is_general' => $employeeShift->is_general,
                'is_unassigned' => $employeeShift->is_unassigned,
                'is_shift' => $employeeShift->is_shift,
                'color_code' => $employeeShift->color_code,
                'is_future_date' => Carbon::now()->toDateString() < $employeeShift->date ? 1 : 0
            ];

            if($employeeShift->is_general){
                $name = 'General';
                $temp_calender['shift_name'] = 'General';
                $temp_calender['can_checkin'] = !$has_checkin && Carbon::now()->toDateString() == $employeeShift->date ? 1 : 0;
                $temp_calender['shift_start'] = $business_start_time;
                $temp_calender['shift_end'] = $business_end_time;
                $shifts[] = $this->makeShiftData($employeeShift->id, $name, $employeeShift->color_code, $business_start_time, $business_end_time);
            }
            elseif($employeeShift->is_shift){
                $shift_start = Carbon::parse($employeeShift->start_time);
                $shift_end = Carbon::parse($employeeShift->end_time);
                $temp_calender['can_checkin'] = !$has_checkin && (Carbon::now()->toTimeString() >= $shift_start && $shift_end >= Carbon::now()->toTimeString()) ? 1 : 0;
                $temp_calender['shift_name'] = $employeeShift->shift->title;
                $temp_calender['shift_start'] = $shift_start->format('h:i A');
                $temp_calender['shift_end'] = $shift_end->format('h:i A');
                $shifts[] = $this->makeShiftData($employeeShift->id, $employeeShift->shift->title, $employeeShift->color_code, $employeeShift->shift->start_time, $employeeShift->shift->end_time);
            }
            elseif($employeeShift->is_unassigned){
                $temp_calender['can_checkin'] = 0;
                $temp_calender['shift_name'] = null;
                $temp_calender['shift_start'] = null;
                $temp_calender['shift_end'] = null;
            }

            $employee_shift_calender[] = $temp_calender;
        }
        $shifts = collect($shifts)->unique('title')->values();
        return ['employee_shifts' =>$employee_shift_calender, 'shifts' => $shifts];
    }

    /**
     * @param $id
     * @param $name
     * @param $color_code
     * @param $start_time
     * @param $end_time
     * @return array
     */
    private function makeShiftData($id, $name, $color_code, $start_time, $end_time)
    {
        return [
            'id'            => $id,
            'title'         => $name,
            'color_code'    => $color_code,
            'start_time'    => Carbon::parse($start_time)->format('h:i A'),
            'end_time'      => Carbon::parse($end_time)->format('h:i A'),
        ];
    }
}
