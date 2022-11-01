<?php namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Sheba\Business\BusinessBasicInformation;
use App\Models\BusinessMember;
use App\Sheba\Employee\ShiftCalender;
use Sheba\Dal\Attendance\EloquentImplementation;
use Sheba\Dal\ShiftAssignment\ShiftAssignmentRepository;
use Sheba\Helpers\TimeFrame;
use Illuminate\Http\Request;

class ShiftCalenderController extends Controller
{
    use BusinessBasicInformation;

    /*** @var ShiftAssignmentRepository*/
    private $shiftAssignmentRepository;

    public function __construct(ShiftAssignmentRepository $shift_assignment_repo)
    {
        $this->shiftAssignmentRepository = $shift_assignment_repo;
    }

    public function index(Request $request, TimeFrame $time_frame, EloquentImplementation $attendanceRepository)
    {
        /** @var Business $business */
        $business = $this->getBusiness($request);
        if (!$business) return api_response($request, null, 401);

        /** @var BusinessMember $business_member */
        $business_member = $this->getBusinessMember($request);
        if (!$business_member) return api_response($request, null, 401);

        $month = $request->month;
        $year = $request->year;
        $time_frame = $time_frame->forAMonth($month, $year);
        $shift_calender = $this->shiftAssignmentRepository->builder()->with('shift')->where('business_member_id', $business_member->id)->whereBetween('date', [$time_frame->start, $time_frame->end])->get();
        $employee_attendances = $attendanceRepository->builder()->where('business_member_id', $business_member->id)->whereBetween('date', [$time_frame->start, $time_frame->end])->pluck('checkin_time', 'date')->toArray();
        $shift_data = new ShiftCalender($business, $shift_calender, $employee_attendances);
        $shift_data = $shift_data->employee_shift_calender();
        return api_response($request, null, 200, ['shift_calender' => $shift_data['employee_shifts'], 'shifts' => $shift_data['shifts']]);
    }
}
