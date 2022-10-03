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

        $shift_calender_data = collect([]);
        foreach ($period as $date) {
            $shift_calender_data->push($this->getQuery($business, $request, $date)->offset($offset)->take($limit)->get());
        }

        $shift_calender_data= $shift_calender_data->flatten(1);

        $total_employees = $this->getQuery($business, $request, $start_date)->count(\DB::raw('DISTINCT business_member_id'));

        return [$shift_calender_data, $total_employees];
    }

    private function getQuery(Business $business, Request $request, Carbon $date)
    {
        $shift_calender = $this->repo->builder()
            ->with('businessMember.member.profile', 'businessMember.role.businessDepartment')
            ->where('date', $date->toDateString())
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

        return $shift_calender;
    }
}
