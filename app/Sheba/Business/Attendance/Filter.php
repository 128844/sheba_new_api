<?php namespace App\Sheba\Business\Attendance;

use Illuminate\Http\Request;

class Filter
{
    /**
     * @param $employee_attendance
     * @return mixed
     */
    public function filterInactiveCoWorkersWithData($employee_attendance)
    {
        return $employee_attendance->filter(function ($attendance) {
            if ($attendance['status'] === 'inactive') {
                return $attendance['attendance']['present'] || $attendance['attendance']['on_leave'];
            } else {
                return true;
            }
        });
    }

    /**
     * @param $employee_attendance
     * @param Request $request
     * @return mixed
     */
    public function searchWithEmployeeName($employee_attendance, Request $request)
    {
        return $employee_attendance->filter(function ($attendance) use ($request) {
            return str_contains(strtoupper($attendance['member']['name']), strtoupper($request->search));
        });
    }

    /**
     * @param $employee_attendance
     * @param string $sort
     * @return mixed
     */
    public function attendanceSortOnAbsent($employee_attendance, $sort = 'asc')
    {
        $sort_by = ($sort === 'asc') ? 'sortBy' : 'sortByDesc';
        return $employee_attendance->$sort_by(function ($attendance, $key) {
            return strtoupper($attendance['attendance']['absent']);
        });
    }

    /**
     * @param $employee_attendance
     * @param string $sort
     * @return mixed
     */
    public function attendanceSortOnPresent($employee_attendance, $sort = 'asc')
    {
        $sort_by = ($sort === 'asc') ? 'sortBy' : 'sortByDesc';
        return $employee_attendance->$sort_by(function ($attendance, $key) {
            return strtoupper($attendance['attendance']['present']);
        });
    }

    /**
     * @param $employee_attendance
     * @param string $sort
     * @return mixed
     */
    public function attendanceSortOnLeave($employee_attendance, $sort = 'asc')
    {
        $sort_by = ($sort === 'asc') ? 'sortBy' : 'sortByDesc';
        return $employee_attendance->$sort_by(function ($attendance, $key) {
            return strtoupper($attendance['attendance']['on_leave']);
        });
    }

    /**
     * @param $employee_attendance
     * @param string $sort
     * @return mixed
     */
    public function attendanceSortOnLate($employee_attendance, $sort = 'asc')
    {
        $sort_by = ($sort === 'asc') ? 'sortBy' : 'sortByDesc';
        return $employee_attendance->$sort_by(function ($attendance, $key) {
            return strtoupper($attendance['attendance']['late']);
        });
    }

    /**
     * @param $attendances
     * @param string $sort
     * @return mixed
     */
    public function attendanceCustomSortOnOvertime($attendances, $sort = 'asc')
    {
        $sort_by = ($sort === 'asc') ? 'sortBy' : 'sortByDesc';
        return $attendances->$sort_by(function ($attendance, $key) {
            return $attendance['attendance']['overtime_in_minutes'];
        });
    }

    /**
     * @param $attendances
     * @param $sort
     * @return mixed
     */
    public function attendanceSortOnDate($attendances, $sort = 'asc')
    {
        $sort_by = ($sort === 'asc') ? 'sortBy' : 'sortByDesc';
        return $attendances->$sort_by(function ($attendance, $key) {
            return strtoupper($attendance['date']);
        });
    }

    /**
     * @param $attendances
     * @param $sort
     * @return mixed
     */
    public function attendanceSortOnHour($attendances, $sort = 'asc')
    {
        $sort_by = ($sort === 'asc') ? 'sortBy' : 'sortByDesc';
        return $attendances->$sort_by(function ($attendance, $key) {
            return strtoupper($attendance['attendance']['active_hours']);
        });
    }

    /**
     * @param $attendances
     * @param $sort
     * @return mixed
     */
    public function attendanceSortOnCheckin($attendances, $sort = 'asc')
    {
        $sort_by = ($sort === 'asc') ? 'sortBy' : 'sortByDesc';
        return $attendances->$sort_by(function ($attendance, $key) {
            return strtoupper($attendance['attendance']['check_in']['time']);
        });
    }

    /**
     * @param $attendances
     * @param $sort
     * @return mixed
     */
    public function attendanceSortOnCheckout($attendances, $sort = 'asc')
    {
        $sort_by = ($sort === 'asc') ? 'sortBy' : 'sortByDesc';
        return $attendances->$sort_by(function ($attendance, $key) {
            return strtoupper($attendance['attendance']['check_out']['time']);
        });
    }

    /**
     * @param $attendances
     * @param string $sort
     * @return mixed
     */
    public function attendanceSortOnOvertime($attendances, $sort = 'asc')
    {
        $sort_by = ($sort === 'asc') ? 'sortBy' : 'sortByDesc';
        return $attendances->$sort_by(function ($attendance, $key) {
            return $attendance['overtime_in_minutes'];
        });
    }
}