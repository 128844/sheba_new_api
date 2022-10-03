<?php namespace App\Http\Controllers\B2b;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Transformers\Business\EmployeeShiftDetailsTransformer;
use App\Transformers\Business\ShiftCalenderTransformer;
use App\Transformers\CustomSerializer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use League\Fractal\Manager;
use Sheba\Business\CoWorker\Statuses;
use Sheba\Business\ShiftSetting\ShiftAssign\Requester;
use Sheba\Business\ShiftSetting\ShiftAssign\Creator;
use Sheba\Business\ShiftSetting\ShiftAssign\ShiftAssignToCalender;
use Sheba\Business\ShiftSetting\ShiftAssign\ShiftRemover;
use Sheba\Dal\BusinessShift\BusinessShiftRepository;
use Sheba\Dal\ShiftAssignment\ShiftAssignmentRepository;
use Sheba\ModificationFields;
use League\Fractal\Resource\Item;
use Sheba\Repositories\Interfaces\Business\DepartmentRepositoryInterface;

class ShiftAssignmentController extends Controller
{
    use ModificationFields;
    /*** @var BusinessShiftRepository $businessShiftRepository*/
    private $businessShiftRepository;
    /** @var Requester $shiftCalenderRequester */
    private $shiftCalenderRequester;
    /** @var Creator $creator */
    private $shiftCalenderCreator;
    /*** @var ShiftRemover $shiftRemover */
    private $shiftRemover;
    /*** @var ShiftAssignmentRepository $shiftAssignmentRepository */
    private $shiftAssignmentRepository;
    /*** @var ShiftAssignToCalender $shiftAssignToCalender */
    private $shiftAssignToCalender;

    public function __construct(ShiftAssignmentRepository $shift_assignment_repository, BusinessShiftRepository $business_shift_repository,
                                Requester $requester, Creator $creator, ShiftRemover $shift_remover, ShiftAssignToCalender $shift_assign_to_calender)
    {
        $this->shiftAssignmentRepository = $shift_assignment_repository;
        $this->businessShiftRepository = $business_shift_repository;
        $this->shiftCalenderRequester = $requester;
        $this->shiftCalenderCreator = $creator;
        $this->shiftRemover = $shift_remover;
        $this->shiftAssignToCalender = $shift_assign_to_calender;
    }

    public function index(Request $request, ShiftAssignmentRepository $shift_assignment_repository)
    {
        /** @var Business $business */
        $business = $request->business;
        if (!$business) return api_response($request, null, 401);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);
        list($offset, $limit) = calculatePagination($request);

        $start_date = $request->start_date ?: Carbon::now()->addDay()->toDateString();
        $end_date = $request->end_date ?: Carbon::now()->addDays(7)->toDateString();

        $shift_calender = $shift_assignment_repository->builder()
            ->with('businessMember.member.profile', 'businessMember.role.businessDepartment')
            ->whereBetween('date', [$start_date, $end_date])
            ->whereHas('businessMember', function ($q) use ($business) {
                $q->where('status', Statuses::ACTIVE)->where('business_id', $business->id);
            });

        if ($request->has('shift_type')) $shift_calender = $shift_calender->where($request->shift_type, 1);

        if ($request->has('department_id')) {
            $shift_calender->whereHas('businessMember', function ($q) use ($request) {
                $q->whereHas('role', function ($q) use ($request) {
                    $q->whereHas('businessDepartment', function ($q) use ($request) {
                        $q->where('business_departments.id', $request->department_id);
                    });
                });
            });
        }

        if ($request->has('search')) {
            $shift_calender->whereHas('businessMember', function ($q) use ($request) {
                $q->whereHas('member.profile', function ($q) use ($request) {
                    $q->where('name', 'LIKE', "%$request->search%");
                })->orWhere('employee_id', 'LIKE', "%$request->search%");
            });
        }

        $total_employees = $shift_calender->count(\DB::raw('DISTINCT business_member_id'));

        $shift_calender_data = (new ShiftCalenderTransformer())->transform($shift_calender->offset($offset)->take($limit)->get());

        return api_response($request, null, 200, [
            'shift_calender_employee' => $shift_calender_data['data'],
            'shift_calender_header' => $shift_calender_data['header'],
            'total_employees' => $total_employees
        ]);
    }

    public function assignShift($business, $calender_id, Request $request)
    {
        $this->validate($request, [
            'shift_id'                  => 'required|integer',
            'repeat'                    => 'boolean',
            'repeat_type'               => 'string',
            'repeat_range'              => 'integer',
            'days'                      => 'array',
            'end_date'                  => 'date_format:Y-m-d'
        ]);

        /** @var Business $business */
        $business = $request->business;
        if (!$business) return api_response($request, null, 401);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);

        $this->setModifier($request->manager_member);
        $business_shift = $this->businessShiftRepository->find($request->shift_id);
        if (!$business_shift) return api_response($request, null, 404);
        $shift_calender = $this->shiftAssignmentRepository->find($calender_id);

        $this->shiftAssignToCalender->checkShiftStartDate($shift_calender->date, $this->shiftCalenderRequester);
        if ($this->shiftCalenderRequester->hasError()) return api_response($request, null, $this->shiftCalenderRequester->getErrorCode(), ['message' => $this->shiftCalenderRequester->getErrorMessage()]);

        $general_to_unassign_data = $this->shiftAssignToCalender->generalToUnassign($shift_calender, $this->shiftCalenderRequester);
        $this->shiftRemover->setShiftCalenderRequester($this->shiftCalenderRequester)->update($general_to_unassign_data);

        $shift_to_unassign_data = $this->shiftAssignToCalender->shiftToUnassign($shift_calender, $this->shiftCalenderRequester, $request);
        $this->shiftRemover->setShiftCalenderRequester($this->shiftCalenderRequester)->update($shift_to_unassign_data);

        $this->shiftCalenderRequester->setShiftId($request->shift_id)
            ->setShiftName($business_shift->name)
            ->setShiftTitle($business_shift->title)
            ->setStartTime($business_shift->start_time)
            ->setEndTime($business_shift->end_time)
            ->setIsHalfDayActivated($business_shift->is_halfday_enable)
            ->setIsCheckinGraceEnable($business_shift->checkin_grace_enable)
            ->setIsCheckoutGraceEnable($business_shift->checkout_grace_enable)
            ->setCheckinGraceTime($business_shift->checkin_grace_time)
            ->setCheckoutGraceTime($business_shift->checkout_grace_time)
            ->setIsGeneralActivated(0)
            ->setIsUnassignedActivated(0)
            ->setRepeat($request->repeat)
            ->setRepeatType($request->repeat_type)
            ->setRepeatRange($request->repeat_range)
            ->setRepeatDays($request->days)
            ->setEndDate($request->end_date)
            ->setIsShiftActivated(1)
            ->setColorCode($business_shift->color_code);

        $this->shiftAssignToCalender->checkShiftRepeat($request, $shift_calender, $business_member, $this->shiftCalenderRequester);
        $shift_calender = $this->shiftCalenderRequester->getData();
        if ($this->shiftCalenderRequester->hasError()) return api_response($request, null, $this->shiftCalenderRequester->getErrorCode(), ['message' => $this->shiftCalenderRequester->getErrorMessage()]);
        $this->shiftCalenderCreator->setShiftCalenderRequester($this->shiftCalenderRequester)->update($shift_calender);
        return api_response($request, null, 200);
    }

    public function assignGeneralAttendance($business, $calender_id, Request $request)
    {
        /** @var Business $business */
        $business = $request->business;
        if (!$business) return api_response($request, null, 401);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);

        $shift_calender = $this->shiftAssignmentRepository->find($calender_id);
        $this->shiftAssignToCalender->checkShiftStartDate($shift_calender->date, $this->shiftCalenderRequester);
        if ($this->shiftCalenderRequester->hasError()) return api_response($request, null, $this->shiftCalenderRequester->getErrorCode(), ['message' => $this->shiftCalenderRequester->getErrorMessage()]);

        $shift_calender = $this->shiftAssignmentRepository->where('business_member_id', $shift_calender->business_member_id)->where('is_shift', 1)->where('date', '>=', $shift_calender->date)->get();
        $this->setModifier($request->manager_member);
        $this->shiftCalenderRequester->setIsHalfDayActivated(0)
            ->setIsGeneralActivated(1)
            ->setIsShiftActivated(0);

        $this->shiftRemover->setShiftCalenderRequester($this->shiftCalenderRequester)->update($shift_calender);
        return api_response($request, null, 200);
    }

    public function unassignShift($business, $calender_id, Request $request)
    {
        /** @var Business $business */
        $business = $request->business;
        if (!$business) return api_response($request, null, 401);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);

        $shift_calender = $this->shiftAssignmentRepository->find($calender_id);
        $this->shiftAssignToCalender->checkShiftStartDate($shift_calender->date, $this->shiftCalenderRequester);
        if ($this->shiftCalenderRequester->hasError()) return api_response($request, null, $this->shiftCalenderRequester->getErrorCode(), ['message' => $this->shiftCalenderRequester->getErrorMessage()]);

        $shift_to_unassign_data = $this->shiftAssignToCalender->shiftToUnassign($shift_calender, $this->shiftCalenderRequester, $request);
        $this->setModifier($request->manager_member);
        $this->shiftRemover->setShiftCalenderRequester($this->shiftCalenderRequester)->update($shift_to_unassign_data);
        return api_response($request, null, 200);
    }

    public function dashboard(Request $request, ShiftAssignmentRepository $shift_assignment_repository)
    {
        /** @var Business $business */
        $business = $request->business;
        if (!$business) return api_response($request, null, 401);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);

        $total_active_employee_ids = $business->getActiveBusinessMember()->pluck('id')->toArray();
        $under_general_attendance_count = $shift_assignment_repository->where('business_member_id', $total_active_employee_ids)->where('is_general', 1)->where('date', '<', Carbon::now()->toDateString())->count();
        $under_shift_count = $shift_assignment_repository->where('business_member_id', $total_active_employee_ids)->where('is_shift', 1)->where('date', '<', Carbon::now()->toDateString())->count();
        $unassigned_shift_count = $shift_assignment_repository->where('business_member_id', $total_active_employee_ids)->where('is_unassigned', 1)->where('date', '>', Carbon::now()->toDateString())->count();

        return api_response($request, null, 200, ['dashboard' => [
            'total_employee' => count($total_active_employee_ids),
            'under_general_attendance' => $under_general_attendance_count,
            'under_shift_count' => $under_shift_count,
            'unassigned_shift_count' => $unassigned_shift_count
        ]]);
    }

    public function details($business, $id, Request $request)
    {
        /** @var Business $business */
        $business = $request->business;
        if (!$business) return api_response($request, null, 401);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);

        $shift_calender = $this->shiftAssignmentRepository->find($id);

        if (!$shift_calender) return api_response($request, null, 404);
        $manager = new Manager();
        $manager->setSerializer(new CustomSerializer());
        $member = new Item($shift_calender, new EmployeeShiftDetailsTransformer());
        $shift_calender = $manager->createData($member)->toArray()['data'];
        return api_response($request, $shift_calender, 200, ['shift_calender' => $shift_calender]);
    }
}
