<?php

namespace Sheba\Business\Attendance\Monthly;

use App\Models\Business;
use App\Models\BusinessMember;
use App\Sheba\Business\Attendance\MonthlyStat;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Sheba\Dal\Attendance\Contract as AttendanceRepoInterface;
use Sheba\Dal\BusinessHoliday\Contract as BusinessHolidayRepoInterface;
use Sheba\Dal\BusinessWeekendSettings\BusinessWeekendSettingsRepo;
use Sheba\Dal\ShiftAssignment\ShiftAssignment;
use Sheba\Helpers\TimeFrame;

class MonthlyAttendanceCalculator
{
    const FIRST_DAY_OF_MONTH = 1;

    /** @var AttendanceRepoInterface */
    private $attendanceRepo;
    /** @var TimeFrame */
    private $timeFrame;
    /** @var BusinessHolidayRepoInterface */
    private $holidayRepo;
    /** @var BusinessWeekendSettingsRepo */
    private $weekendSettingsRepo;

    public function __construct(
        AttendanceRepoInterface $attendance_repo,
        TimeFrame $time_frame,
        BusinessHolidayRepoInterface $business_holiday_repo,
        BusinessWeekendSettingsRepo $business_weekend_settings_repo
    ) {
        $this->attendanceRepo = $attendance_repo;
        $this->timeFrame = $time_frame;
        $this->holidayRepo = $business_holiday_repo;
        $this->weekendSettingsRepo = $business_weekend_settings_repo;
    }

    public function calculate($business, Request $request)
    {
        list($offset, $limit) = calculatePagination($request);

        /** @var Business $business */
        $business = Business::where('id', (int)$business)->select('id', 'name', 'phone', 'email', 'type', 'is_shift_enable')->first();

        $business_members = $business->getAllBusinessMemberExceptInvited();

        $business_holiday = $this->holidayRepo->getAllByBusiness($business);

        if ($request->has('department_id') && $request->department_id != 'null') {
            $business_members = $business_members->whereHas('role', function ($q) use ($request) {
                $q->whereHas('businessDepartment', function ($q) use ($request) {
                    $q->where('business_departments.id', $request->department_id);
                });
            });
        }

        if ($request->has('status') && $request->status != 'null') {
            $business_members = $business_members->where('status', $request->status);
        }

        $final_business_members = collect();

        if ($request->has('file')) {
            $business_members->chunk(100, function ($business_member_chunk) use (&$final_business_members) {
                $final_business_members = $final_business_members->merge($business_member_chunk);
            });
        } else {
            $final_business_members = $business_members->get();
        }

        $total_business_members_count = $final_business_members->count();

        if ($request->has('limit') && !$request->has('file')) $final_business_members = $final_business_members->splice($offset, $limit);

        $all_employee_attendance = [];
        $weekend_settings = $this->weekendSettingsRepo->getAllByBusiness($business);
        if ($request->has('start_date') && $request->has('end_date')) {
            $start_date = $request->start_date;
            $end_date = $request->end_date;
        } else {
            $start_date = Carbon::now()->startOfMonth()->toDateString();
            $end_date = Carbon::now()->endOfMonth()->toDateString();
        }
        foreach ($final_business_members as $business_member) {
            $member = $business_member->member;
            $profile = $member->profile;
            $member_name = $profile->name;
            /** @var BusinessMember $business_member */
            $member_department = $business_member->role ? $business_member->role->businessDepartment : null;
            $department_name = $member_department ? $member_department->name : 'N/S';
            $department_id = $member_department ? $member_department->id : 'N/S';
            $business_member_joining_date = $business_member->join_date;
            $businessMemberDesignation = $business_member->role ? $business_member->role->name : 'N/S';
            /** @var BusinessMember $line_manager */
            $line_manager = $business_member->manager()->first();
            $businessMemberLineManager = $line_manager ? $line_manager->member->profile->name : 'N/S';
            $businessMemberLineManagerEmail = $line_manager ? $line_manager->member->profile->email : 'N/S';
            $joining_prorated = null;
            $member_start_date = Carbon::parse($start_date);
            if ($this->checkJoiningDate($business_member_joining_date, $start_date, $end_date)) {
                $joining_prorated = 1;
                $member_start_date = $business_member_joining_date;
            }
            $time_frame = $this->timeFrame->forDateRange($member_start_date, $end_date);

            $business_member_leave = $business_member->leaves()->accepted()->where(function ($query) use ($time_frame){
                $query->whereBetween('start_date', [$time_frame->start, $time_frame->end]);
                $query->orWhereBetween('end_date', [$time_frame->start, $time_frame->end]);
            })->get();

            $attendances = $this->attendanceRepo->getAllAttendanceByBusinessMemberFilteredWithYearMonth($business_member, $time_frame);
            $business_member_shifts = null;
            if ($is_shift_enable = $business->isShiftEnable()) {
                $business_member_shifts = $this->loadShifts($business_member);
            }
            $employee_attendance = (new MonthlyStat($time_frame, $business, $weekend_settings, $business_member_leave, false, $is_shift_enable))
                ->setBusinessHolidays($business_holiday)->transform($attendances, $business_member_shifts);

            $all_employee_attendance[] = [
                'business_member_id' => $business_member->id,
                'employee_id' => $business_member->employee_id ?: 'N/A',
                'email' => $profile->email,
                'designation' => $businessMemberDesignation,
                'address' => $profile->address,
                'status' => $business_member->status,
                'line_manager' => $businessMemberLineManager,
                'line_manager_email' => $businessMemberLineManagerEmail,
                'member' => [
                    'id' => $member->id,
                    'name' => $member_name,
                ],
                'department' => [
                    'id' => $department_id,
                    'name' => $department_name,
                ],
                'attendance' => $employee_attendance['statistics'],
                'joining_prorated' => $joining_prorated ? 'Yes' : 'No'
            ];
        }

        $all_employee_attendance = collect($all_employee_attendance);

        $all_employee_attendance = $this->filterInactiveCoWorkersWithData($all_employee_attendance);

        if ($request->has('search') && $request->search != 'null') $all_employee_attendance = $this->searchWithEmployeeName($all_employee_attendance, $request);
        if ($request->has('sort_on_absent')) $all_employee_attendance = $this->attendanceSortOnAbsent($all_employee_attendance, $request->sort_on_absent);
        if ($request->has('sort_on_present')) $all_employee_attendance = $this->attendanceSortOnPresent($all_employee_attendance, $request->sort_on_present);
        if ($request->has('sort_on_leave')) $all_employee_attendance = $this->attendanceSortOnLeave($all_employee_attendance, $request->sort_on_leave);
        if ($request->has('sort_on_late')) $all_employee_attendance = $this->attendanceSortOnLate($all_employee_attendance, $request->sort_on_late);
        if ($request->has('sort_on_overtime')) $all_employee_attendance = $this->attendanceCustomSortOnOvertime($all_employee_attendance, $request->sort_on_overtime);

        return [$all_employee_attendance, $total_business_members_count, $start_date, $end_date];
    }

    private function loadShifts(BusinessMember $businessMember)
    {
        return $businessMember->shifts()
            ->selectTypes()
            ->within($this->timeFrame)
            ->selectBusinessMember()
            ->selectDate()
            ->get()
            ->toAssocFromKey(function (ShiftAssignment $assignment) {
                return $assignment->getDate()->toDateString();
            });
    }


    private function checkJoiningDate($business_member_joining_date, $start_date, $end_date)
    {
        if (!$business_member_joining_date) return false;
        if ($business_member_joining_date->format('d') == self::FIRST_DAY_OF_MONTH) return false;
        return $business_member_joining_date->format('Y-m-d') >= $start_date && $business_member_joining_date->format('Y-m-d') <= $end_date;
    }

    /**
     * @param $employee_attendance
     * @return mixed
     */
    private function filterInactiveCoWorkersWithData($employee_attendance)
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
    private function searchWithEmployeeName($employee_attendance, Request $request)
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
    private function attendanceSortOnAbsent($employee_attendance, $sort = 'asc')
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
    private function attendanceSortOnPresent($employee_attendance, $sort = 'asc')
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
    private function attendanceSortOnLeave($employee_attendance, $sort = 'asc')
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
    private function attendanceSortOnLate($employee_attendance, $sort = 'asc')
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
    private function attendanceCustomSortOnOvertime($attendances, $sort = 'asc')
    {
        $sort_by = ($sort === 'asc') ? 'sortBy' : 'sortByDesc';
        return $attendances->$sort_by(function ($attendance, $key) {
            return $attendance['attendance']['overtime_in_minutes'];
        });
    }
}
