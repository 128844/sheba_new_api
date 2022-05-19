<?php namespace App\Http\Controllers\B2b;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Transformers\Business\EmployeeShiftDetailsTransformer;
use App\Transformers\CustomSerializer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use League\Fractal\Manager;
use Sheba\Business\ShiftSetting\ShiftAssign\Requester;
use Sheba\Business\ShiftSetting\ShiftAssign\Creator;
use Sheba\Business\ShiftSetting\ShiftAssign\ShiftRemover;
use Sheba\Dal\BusinessShift\BusinessShiftRepository;
use Sheba\Dal\ShiftCalender\ShiftCalenderRepository;
use Sheba\ModificationFields;
use League\Fractal\Resource\Item;

class ShiftCalenderController extends Controller
{
    use ModificationFields;
    private $shiftCalenderRepository;
    private $businessShiftRepository;
    private $shiftCalenderRequester;
    /** @var Creator  */
    private $shiftCalenderCreator;
    private $shiftRemover;

    public function __construct()
    {
        $this->shiftCalenderRepository = app(ShiftCalenderRepository::class);
        $this->businessShiftRepository = app(BusinessShiftRepository::class);
        $this->shiftCalenderRequester = app(Requester::class);
        $this->shiftCalenderCreator = app(Creator::class);
        $this->shiftRemover = app(ShiftRemover::class);
    }

    public function index(Request $request, ShiftCalenderRepository $shift_calender_repository)
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
        $active_business_member_ids = $business->getActiveBusinessMember()->pluck('id')->toArray();
        $shift_calender = $shift_calender_repository->builder()->whereIn('business_member_id', $active_business_member_ids)->whereBetween('date', [$start_date, $end_date]);
        $shift_calender_data = (new ShiftCalenderTransformer())->transform($shift_calender->get());
        $total_data = count($shift_calender_data['data']);
        $shift_calender_employee_data = collect($shift_calender_data['data'])->splice($offset, $limit)->toArray();
        usort($shift_calender_employee_data, array($this,'employeeSortByPDisplayPriority'));
        return api_response($request, null, 200, ['shift_calender_employee' => $shift_calender_employee_data, 'shift_calender_header' => $shift_calender_data['header'], 'total' => $total_data]);
    }

    public function assignShift($business, $id, Request $request)
    {
        $this->validate($request, [
            'shift_id'                  => 'required|integer',
            'date'                      => 'required|date_format:Y-m-d'
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

        $business_shift = $this->businessShiftRepository->find($request->shift_id);
        $shift_calender = $this->shiftCalenderRepository->find($id);

        $this->shiftCalenderRequester->setShiftId($request->shift_id)
            ->setShiftName($business_shift->name)
            ->setStartTime($business_shift->start_time)
            ->setEndTime($business_shift->end_time)
            ->setIsHalfDayActivated($business_shift->is_halfday_enable)
            ->setIsGeneralActivated(0)
            ->setIsUnassignedActivated(0)
            ->setIsShiftActivated(1)
            ->setColorCode($business_shift->color_code);

        return json_encode($this->shiftCalenderRequester->getEndTime());
        $this->shiftCalenderCreator->setShiftCalenderRequester($this->shiftCalenderRequester)->update($shift_calender);
        return api_response($request, null, 200);
    }

    public function assignGeneralAttendance($business, $id, Request $request)
    {
        /** @var Business $business */
        $business = $request->business;
        if (!$business) return api_response($request, null, 401);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);

        $shift_calender = $this->shiftCalenderRepository->find($id);
        $shift_calender = $this->shiftCalenderRepository->where('business_member_id', $shift_calender->business_member_id)->where('is_shift', 1)->get();

        $this->setModifier($request->manager_member);
        $this->shiftCalenderRequester->setShiftId(null)
            ->setShiftName(null)
            ->setStartTime(null)
            ->setEndTime(null)
            ->setIsHalfDayActivated(0)
            ->setIsGeneralActivated(1)
            ->setIsShiftActivated(0)
            ->setColorCode(null);

        foreach($shift_calender as $as_shift)
        {
            $this->shiftRemover->setShiftCalenderRequester($this->shiftCalenderRequester)->update($as_shift);
        }
        return api_response($request, null, 200);
    }

    public function details($business, $id, Request $request)
    {
        /** @var Business $business */
        $business = $request->business;
        if (!$business) return api_response($request, null, 401);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);

        $shift_calender = $this->shiftCalenderRepository->find($id);

        if (!$shift_calender) return api_response($request, null, 404);
        $manager = new Manager();
        $manager->setSerializer(new CustomSerializer());
        $member = new Item($shift_calender, new EmployeeShiftDetailsTransformer());
        $shift_calender = $manager->createData($member)->toArray()['data'];
        return api_response($request, $shift_calender, 200, ['shift_calender' => $shift_calender]);
    }

    private function employeeSortByPDisplayPriority($a, $b)
    {
        if ($a['display_priority'] < $b['display_priority']) return 0;
        return 1;
    }
}
