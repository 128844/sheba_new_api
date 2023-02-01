<?php namespace Sheba\Business\Attendance\Monthly;

use Carbon\Carbon;
use Excel as MonthlyExcel;

class Excel
{
    private $monthlyData;
    private $data = [];
    private $startDate;
    private $endDate;


    public function setMonthlyData(array $monthly_data)
    {
        $this->monthlyData = $monthly_data;
        return $this;
    }

    public function setStartDate($start_date)
    {
        $this->startDate = $start_date;
        return $this;
    }

    public function setEndDate($end_date)
    {
        $this->endDate = $end_date;
        return $this;
    }

    public function get()
    {
        $this->makeReport()->download('xlsx');
    }

    public function save($type)
    {
        if ($type == "email") $this->makeReport()->save();
        return $this->makeReport()->download();
    }

    private function makeReport()
    {
        $this->makeData();
        $file_name = 'Custom_attendance_report';
        $sheet_name = $this->startDate . ' - ' . $this->endDate;

        return MonthlyExcel::create($file_name, function ($excel) use ($sheet_name) {
            $excel->sheet($sheet_name, function ($sheet) {
                $sheet->fromArray($this->data, null, 'A1', true, false);
                $sheet->prependRow($this->getHeaders());
                $sheet->freezeFirstRow();
                $sheet->cell('A1:R1', function ($cells) {
                    $cells->setFontWeight('bold');
                });
                $sheet->getDefaultStyle()->getAlignment()->applyFromArray(
                    array('horizontal' => 'left')
                );
                $sheet->setAutoSize(true);
            });
        });
    }

    private function makeData()
    {
        foreach ($this->monthlyData as $employee) {
            $this->data[] = [
                'employee_id' => $employee['employee_id'],
                'name' => $employee['member']['name'],
                'email' => $employee['email'],
                'dept' => $employee['department']['name'],
                'working_days' => $employee['attendance']['working_days'],
                'present' => $employee['attendance']['present'],
                'on_time' => $employee['attendance']['on_time'],
                'late' => $employee['attendance']['late'],
                'left_timely' => $employee['attendance']['left_timely'],
                'left_early' => $employee['attendance']['left_early'],
                'on_leave' => $employee['attendance']['on_leave'],
                'absent' => $employee['attendance']['absent'],
                'total_hours' => $employee['attendance']['total_hours'],
                'overtime' => $employee['attendance']['overtime'],
                'remote_checkin' => $employee['attendance']['remote_checkin'],
                'office_checkin' => $employee['attendance']['office_checkin'],
                'remote_checkout' => $employee['attendance']['remote_checkout'],
                'office_checkout' => $employee['attendance']['office_checkout'],
                'joining_prorated' => $employee['joining_prorated'],
                'leave_days' => $employee['attendance']['leave_days'],
                'late_days' => $employee['attendance']['late_days'],
                'absent_days' => $employee['attendance']['absent_days']
            ];
        }
    }

    private function getHeaders()
    {
        return ['Employee ID', 'Employee Name', 'Employee Email', 'Department', 'Working Days', 'Present', 'On time', 'Late', 'Left Timely', 'Left early', 'On leave', 'Absent', 'Total Hours', 'Overtime', 'Total Remote Checkin', 'Total Office Checkin', 'Total Remote Checkout', 'Total Office Checkout', 'Joining Prorated', 'Leave Days', 'Late Days', 'Absent Days'];
    }
}
