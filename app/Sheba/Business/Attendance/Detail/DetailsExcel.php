<?php namespace Sheba\Business\Attendance\Detail;

use Carbon\Carbon;
use Excel;

class DetailsExcel
{
    private $breakdownData;
    private $data = [];
    private $checkInTime;
    private $checkInStatus;
    private $checkInLocation;
    private $checkInAddress;
    private $checkOutTime;
    private $checkOutStatus;
    private $checkOutLocation;
    private $checkOutAddress;
    private $totalHours;
    private $lateNote;
    private $leftEarlyNote;
    private $businessMember;
    private $department;
    private $profile;
    private $startDate;
    private $endDate;
    private $overtime;
    private $attendanceReconciled;

    public function __construct()
    {
    }

    public function setBreakDownData(array $detailed_data)
    {
        $this->breakdownData = $detailed_data;
        return $this;
    }

    public function setBusinessMember($business_member)
    {
        $this->businessMember = $business_member;
        $this->profile = $this->businessMember->member->profile;
        $this->department = $this->businessMember->department();
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

    public function setDepartment($department)
    {
        $this->department = $department;
        return $this;
    }

    public function download()
    {
        $this->makeData();

        $file_name = $this->businessMember->employee_id ?
            $this->profile->name . '_' . $this->department->name . '_' . $this->businessMember->employee_id :
            $this->profile->name . '_' . $this->department->name;

        $sheet_name = $this->startDate . ' - ' . $this->endDate;

        Excel::create($file_name, function ($excel) use ($sheet_name) {
            $excel->sheet($sheet_name, function ($sheet) {
                $sheet->fromArray($this->data, null, 'A1', false, false);
                $sheet->prependRow($this->getHeaders());
                $sheet->freezeFirstRow();
                $sheet->cell('A1:O1', function ($cells) {
                    $cells->setFontWeight('bold');
                });
                $sheet->setAutoSize(true);
            });
        })->export('xlsx');
    }

    private function initiateRow()
    {
        return [
            'date' => null,
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
            'lateNote' => null,
            'leftEarlyNote' => null,
            'attendanceReconciled' => '-'
        ];
    }

    private function makeData()
    {
        foreach ($this->breakdownData as $attendance) {
            $row = $this->initiateRow();

            if (!$attendance['weekend_or_holiday_tag']) {
                if ($attendance['show_attendance'] == 1) {
                    $row['date'] = $attendance['date'];
                    $this->checkInOutLogics($attendance, $row);
                    $row['status'] = 'Present';
                }
                if ($attendance['show_attendance'] == 0) {
                    if ($attendance['is_absent'] == 1) {
                        $row['date'] = $attendance['date'];
                        $row['status'] = 'Absent';
                    }
                }
            } else {
                $row['date'] = $attendance['date'];
                if ($attendance['show_attendance'] == 1) {
                    $this->checkInOutLogics($attendance, $row);
                }
                if ($attendance['weekend_or_holiday_tag'] === 'weekend') {
                    $row['status'] = 'Weekend';
                } else if ($attendance['weekend_or_holiday_tag'] === 'holiday') {
                    $row['status'] = 'Holiday';
                } else if ($attendance['weekend_or_holiday_tag'] === 'full_day') {
                    $row['status'] = 'On leave: full day';
                } else if ($attendance['weekend_or_holiday_tag'] === 'first_half' || $attendance['weekend_or_holiday_tag'] === 'second_half') {
                    $row['status'] = "On leave: half day";
                }
            }
            $this->data[] = [
                'date' => $row['date'],
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
                'attendance_reconciled' => $row['attendanceReconciled'],
                'shift_name' => $this->getShiftName($attendance)
            ];
        }
    }

    private function getHeaders()
    {
        return [
            'Date', 'Status', 'Check in time', 'Check in status', 'Check in location',
            'Check in address', 'Check out time', 'Check out status',
            'Check out location', 'Check out address', 'Total Hours', 'Overtime',
            'Late check in note', 'Left early note', 'Attendance Reconciliation', 'Shift Name'
        ];
    }

    private function checkInOutLogics($attendance, &$row)
    {
        $attendance_check_in = $attendance['attendance']['check_in'];
        $attendance_check_out = $attendance['attendance']['check_out'];

        $row['checkInTime'] = $attendance_check_in['time'];
        if ($attendance_check_in['status'] === 'late') {
            $row['checkInStatus'] = 'Late';
        }
        if ($attendance_check_in['status'] === 'on_time') {
            $row['checkInStatus'] = 'On time';
        }

        if ($attendance_check_in['is_remote']) {
            $row['checkInLocation'] = "Remote";
        } else if ($attendance_check_in['is_in_wifi']) {
            $row['checkInLocation'] = "Office IP";
        } else if ($attendance_check_in['is_geo']) {
            $row['checkInLocation'] = "Geo Location";
        }

        if ($attendance_check_in['address']) {
            $row['checkInAddress'] = $attendance_check_in['address'];
        }

        if (!is_null($attendance_check_out)) {
            $row['checkOutTime'] = $attendance_check_out['time'];

            if ($attendance_check_out['status'] === 'left_early') {
                $row['checkOutStatus'] = 'Left early';
            }

            if ($attendance_check_out['status'] === 'left_timely') {
                $row['checkOutStatus'] = 'Left timely';
            }

            if ($attendance_check_in['is_remote']) {
                $row['checkOutLocation'] = "Remote";
            } else if ($attendance_check_in['is_in_wifi']) {
                $row['checkOutLocation'] = "Office IP";
            } else if ($attendance_check_in['is_geo']) {
                $row['checkOutLocation'] = "Geo Location";
            }

            if ($attendance_check_out['address']) {
                $row['checkOutAddress'] = $attendance_check_out['address'];
            }
        }

        if ($attendance['attendance']['active_hours']) {
            $row['totalHours'] = $attendance['attendance']['active_hours'];
        }

        if ($attendance['attendance']['overtime_in_minutes']) {
            $row['overtime'] = $attendance['attendance']['overtime'];
        }

        $row['lateNote'] = $attendance['attendance']['late_note'];
        $row['leftEarlyNote'] = $attendance['attendance']['left_early_note'];
        $row['attendanceReconciled'] = $attendance['attendance']['is_attendance_reconciled'] ? 'Yes' : 'No';
    }

    private function getShiftName($attendance)
    {
        if (!$attendance['attendance']) return '-';

        if ($attendance['attendance']['shift']['is_general'] == 1) return "General";

        if ($attendance['attendance']['shift']['is_unassigned'] == 1) return "Unassigned";

        return $attendance['attendance']['shift']['details']['name'];
    }
}
