<?php namespace Sheba\Business\Attendance\Daily;

use Excel;

class DailyExcel
{
    private $dailyData;
    private $data = [];
    private $date;

    private function initializeData()
    {
        return [
            'employeeId' => null,
            'employeeName' => null,
            'department' => null,
            'employee_address' => null,
            'status' => null,
            'checkInTime' => '-',
            'checkInStatus' => '-',
            'checkInLocation' => '-',
            'checkInAddress' => '-',
            'checkOutTime' => '-',
            'checkOutStatus' => '-',
            'checkOutLocation' => '-',
            'checkOutAddress' => '-',
            'totalHours' => '-',
            'overtime' => '-',
            'lateNote' => '-',
            'leftEarlyNote' => '-'
        ];
    }

    public function setData(array $daily_data)
    {
        $this->dailyData = $daily_data;
        return $this;
    }

    public function setDate($date)
    {
        $this->date = $date;
        return $this;
    }

    public function download()
    {
        $this->makeData();
        $file_name = 'Daily_attendance_report';
        Excel::create($file_name, function ($excel) {
            $excel->sheet('data', function ($sheet) {
                $sheet->fromArray($this->data, null, 'A1', false, false);
                $sheet->prependRow($this->getHeaders());
                $sheet->freezeFirstRow();
                $sheet->cell('A1:Z1', function ($cells) {
                    $cells->setFontWeight('bold');
                });
                $sheet->getDefaultStyle()->getAlignment()->applyFromArray([
                    'horizontal' => 'left'
                ]);
                $sheet->setAutoSize(true);
            });
        })->export('xlsx');
    }

    private function makeData()
    {
        foreach ($this->dailyData as $attendance) {
            $row = $this->initializeData();
            if (!is_null($attendance['check_in']) && !$attendance['is_absent']) {
                if ($attendance['is_half_day_leave']) {
                    $row['status'] = "On leave: half day";
                } else {
                    $row['status'] = 'Present';
                }

                $row['checkInTime'] = $attendance['check_in']['checkin_time'];
                if ($attendance['check_in']['status'] == 'late') {
                    $row['checkInStatus'] = "Late";
                }
                if ($attendance['check_in']['status'] == 'on_time') {
                    $row['checkInStatus'] = "On time";
                }
                if ($attendance['check_in']['is_remote']) {
                    $row['checkInLocation'] = "Remote";
                } else if ($attendance['check_in']['is_in_wifi']) {
                    $row['checkInLocation'] = "Office IP";
                } else if ($attendance['check_in']['is_geo']) {
                    $row['checkInLocation'] = "Geo Location";
                }

                $row['checkInAddress'] = $attendance['check_in']['address'];
                if (!is_null($attendance['check_out'])) {
                    $row['checkOutTime'] = $attendance['check_out']['checkout_time'];

                    if ($attendance['check_out']['status'] == 'left_early') {
                        $row['checkOutStatus'] = 'Left early';
                    }
                    if ($attendance['check_out']['status'] == 'left_timely') {
                        $row['checkOutStatus'] = 'Left timely';
                    }
                    if ($attendance['check_out']['is_remote']) {
                        $row['checkOutLocation'] = "Remote";
                    } else if ($attendance['check_out']['is_in_wifi']) {
                        $row['checkOutLocation'] = "Office IP";
                    } else if ($attendance['check_out']['is_geo']) {
                        $row['checkOutLocation'] = "Geo Location";
                    }
                    $row['checkOutAddress'] = $attendance['check_out']['address'];
                }

                $row['totalHours'] = $attendance['active_hours'];
                $row['overtime'] = $attendance['overtime'];
                $row['lateNote'] = $attendance['check_in']['note'];
                $row['leftEarlyNote'] = $attendance['check_out']['note'];
            }

            if ($attendance['is_absent']) {
                $row['status'] = "Absent";
            }
            if ($attendance['is_on_leave']) {
                if (!$attendance['is_half_day_leave']) {
                    $row['status'] = "On leave: full day";
                } else {
                    $row['status'] = "On leave: half day";
                }
            }

            $this->data[] = [
                'date' => $attendance['date'] ?: $this->date,
                'employee_id' => $attendance['employee_id'],
                'employee_name' => $attendance['member']['name'],
                'department' => $attendance['department']['name'],
                'employee_address' => $attendance['employee_address'],
                'status' => $row['status'],
                'check_in_time' => $row['checkInTime'],
                'check_in_status' => $row['checkInStatus'],
                'check_in_location' => $row['checkInLocation'],
                'check_in_address' => $row['checkInAddress'],
                'check_out_time' => $row['checkOutTime'],
                'check_out_status' => $row['checkOutStatus'],
                'check_out_location' => $row['checkOutLocation'],
                'check_out_address' => $row['checkOutAddress'],

                'total_hours' => $row['totalHours'],
                'overtime' => $row['overtime'],
                'late_check_in_note' => $row['lateNote'],
                'left_early_note' => $row['leftEarlyNote'],
                'attendance_reconciled' => $this->getAttendanceReconciliation($attendance),
                'shift_name' => $this->getShiftName($attendance)
            ];
        }
    }

    private function getHeaders()
    {
        return [
            'Date', 'Employee ID', 'Employee Name', 'Department', 'Address',
            'Status', 'Check in time', 'Check in status', 'Check in location',
            'Check in address', 'Check out time', 'Check out status',
            'Check out location', 'Check out address', 'Total Hours', 'Overtime',
            'Late check in note', 'Left early note', 'Attendance Reconciliation', 'Shift Name'
        ];
    }

    private function getAttendanceReconciliation($attendance)
    {
        if (!isset($attendance['is_attendance_reconciled'])) return '-';

        return $attendance['is_attendance_reconciled'] ? 'Yes' : 'No';
    }

    private function getShiftName($attendance)
    {
        if ($attendance['shift']['is_general'] == 1) return "General";

        if ($attendance['shift']['is_unassigned'] == 1) return "Unassigned";

        return $attendance['shift']['details']['name'];
    }
}
