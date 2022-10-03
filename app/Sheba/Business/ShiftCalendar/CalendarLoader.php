<?php namespace Sheba\Business\ShiftCalendar;


use App\Models\Business;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Sheba\Business\CoWorker\Statuses;
use Sheba\Dal\ShiftAssignment\ShiftAssignmentRepository;

class CalendarLoader
{
    /** @var ShiftAssignmentRepository  */
    private $repo;

    public function __construct(ShiftAssignmentRepository $repo)
    {
        $this->repo = $repo;
    }

    public function load(Business $business, Request $request)
    {
        list($offset, $limit) = calculatePagination($request);

        $start_date = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->addDay();
        $end_date = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now()->addDays(7);

        $period = CarbonPeriod::create($start_date, $end_date);

        $business_member_ids = $this->getBusinessMembersWhoHasShiftType($business, $request, $period);

        $shift_calender_data = collect([]);
        foreach ($period as $date) {
            $data = $this->getQuery($business, $request, $business_member_ids)
                ->where('date', $date->toDateString())
                ->offset($offset)
                ->take($limit)
                ->get();
            $shift_calender_data->push($data);
        }

        $shift_calender_data= $shift_calender_data->flatten(1);

        $total_employees = $this->getQuery($business, $request)
            ->where('date', $start_date->toDateString())
            ->count(\DB::raw('DISTINCT business_member_id'));

        return [$shift_calender_data, $total_employees];
    }

    private function getBusinessMembersWhoHasShiftType(Business $business, Request $request, CarbonPeriod $period)
    {
        if (!$request->has('shift_type')) return null;

        return $this->getQuery($business, $request)
            ->whereBetween('date', [$period->getStartDate()->toDateString(), $period->getEndDate()->toDateString()])
            ->where($request->shift_type, 1)
            ->groupBy('business_member_id')
            ->pluck('business_member_id')
            ->toArray();
    }

    private function getQuery(Business $business, Request $request, $business_member_ids = null)
    {
        $shift_calender = $this->repo->builder()
            ->with('businessMember.member.profile', 'businessMember.role.businessDepartment')
            ->whereHas('businessMember', function ($q) use ($business) {
                $q->where('status', Statuses::ACTIVE)->where('business_id', $business->id);
            });

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

        if (!empty($business_member_ids)) {
            $shift_calender->whereIn('business_member_id', $business_member_ids);
        }

        return $shift_calender;
    }
}
