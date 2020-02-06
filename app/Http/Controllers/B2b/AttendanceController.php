<?php namespace App\Http\Controllers\B2b;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Sheba\Business\Attendance\MonthlyStat;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Sheba\Business\Attendance\DailyStat as AttendanceDailyStat;
use Sheba\Dal\Attendance\Contract as AttendanceRepoInterface;
use Sheba\Dal\Attendance\Statuses;
use Sheba\Dal\BusinessHoliday\Contract as BusinessHolidayRepoInterface;
use Sheba\Dal\BusinessWeekend\Contract as BusinessWeekendRepoInterface;
use Sheba\Helpers\TimeFrame;

class AttendanceController extends Controller
{

    public function getDailyStats($business, Request $request, AttendanceDailyStat $stat)
    {
        $this->validate($request, [
            'status' => 'string|in:' . implode(',', Statuses::get()),
            'business_department_id' => 'numeric',
            'date' => 'date|date_format:Y-m-d',
        ]);
        $date = $request->has('date') ? Carbon::parse($request->date) : Carbon::now();
        $attendances = $stat->setBusiness($request->business)->setDate($date)->setBusinessDepartment($request->business_department_id)->setStatus($request->status)->get();
        if (count($attendances) == 0) return api_response($request, null, 404);
        return api_response($request, null, 200, ['attendances' => $attendances]);
    }

    public function getMonthlyStats($business, Request $request, AttendanceRepoInterface $attendance_repo, TimeFrame $time_frame, BusinessHolidayRepoInterface $business_holiday_repo,
                                    BusinessWeekendRepoInterface $business_weekend_repo)
    {
        try {
            list($offset, $limit) = calculatePagination($request);
            $business = Business::findOrFail((int)$business);
            #$members = $business->members()->limit(20)->get();
            $members = $business->members()->with(['profile' => function ($q) {
                $q->select('id', 'name', 'mobile', 'email');
            }]);
            /*if ($request->has('department_id')) {
                $members = $members->whereHas('businessMember', function ($q) use ($request) {
                    $q->whereHas('role', function ($q) use ($request) {
                        $q->whereHas('businessDepartment', function ($q) use ($request) {
                            $q->where('businessDepartment.id', $request->department_id);
                        });
                    });
                });
            }*/
            $members = $members->get();
            $total_members = $members->count();
            if ($request->has('limit')) $members = $members->splice($offset, $limit);

            $all_employee_attendance = [];

            $year = (int)date('Y');
            $month = (int)date('m');
            if ($request->has('month')) $month = $request->month;
            foreach ($members as $member) {
                $member_name = $member->getIdentityAttribute();
                $business_member = $member->businessMember;
                $member_department = $business_member->department() ? $business_member->department() : null;
                $department_name = $member_department ? $member_department->name : 'N/S';
                $department_id = $member_department ? $member_department->id : 'N/S';

                $time_frame = $time_frame->forAMonth($month, $year);
                $time_frame->end = $this->isShowRunningMonthsAttendance($year, $month) ? Carbon::now() : $time_frame->end;
                $attendances = $attendance_repo->getAllAttendanceByBusinessMemberFilteredWithYearMonth($business_member, $time_frame);

                $business_holiday = $business_holiday_repo->getAllByBusiness($business_member->business);
                $business_weekend = $business_weekend_repo->getAllByBusiness($business_member->business);

                $employee_attendance = (new MonthlyStat($time_frame, $business_holiday, $business_weekend, false))->transform($attendances);

                array_push($all_employee_attendance, [
                    'business_member_id' => $business_member->id,
                    'member' => [
                        'id' => $member->id,
                        'name' => $member_name,
                    ],
                    'department' => [
                        'id' => $department_id,
                        'name' => $department_name,
                    ],
                    'attendance' => $employee_attendance['statistics']
                ]);
            }

            if (count($all_employee_attendance) > 0) return api_response($request, $all_employee_attendance, 200, [
                'all_employee_attendance' => $all_employee_attendance,
                'total_members' => $total_members,
            ]);
            else  return api_response($request, null, 404);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param $month
     * @param $year
     * @return bool
     */
    private function isShowRunningMonthsAttendance($year, $month)
    {
        return (Carbon::now()->month == (int)$month && Carbon::now()->year == (int)$year);
    }
}