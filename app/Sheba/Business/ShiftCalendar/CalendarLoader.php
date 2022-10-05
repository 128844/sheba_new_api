<?php namespace Sheba\Business\ShiftCalendar;


use App\Models\Business;
use App\Models\BusinessMember;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Sheba\Business\BusinessMember\ProfileAndDepartmentQuery;
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
        $period = $this->getPeriod($request);
        list($count, $business_members) = $this->getBusinessMembers($business, $request, $period);

        $business_members = $business_members->isEmpty()
            ? collect([])
            : $this->mapBusinessMembersWithAssignments($business_members, $period);

        return [$period, $business_members, $count];
    }

    private function getPeriod(Request $request)
    {
        $start_date = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->addDay();
        $end_date = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now()->addDays(7);

        return CarbonPeriod::create($start_date, $end_date);
    }

    private function getBusinessMembers(Business $business, Request $request, CarbonPeriod $period)
    {
        list($offset, $limit) = calculatePagination($request);

        $query = $business
            ->getActiveBusinessMember($this->buildProfileQueryRequest($request))
            ->select('id', 'member_id', 'business_id', 'employee_id', 'business_role_id');

        $count = $query->count();

        if ($request->has('shift_type')) {
            $shift_assignees = $this->getShiftAssignees($query, $period, $request->shift_type);

            if (empty($shift_assignees)) return [$count, collect([])];

            $query->whereIn('id', $shift_assignees);
        }

        $business_members = $query->offset($offset)->take($limit)->get();

        return [$count, $business_members];
    }

    private function buildProfileQueryRequest(Request $request)
    {
        $profile_query = new ProfileAndDepartmentQuery();
        if ($request->has('search')) $profile_query->searchTerm = $request->search;
        if ($request->has('department_id')) $profile_query->department = $request->department_id;
        return $profile_query;
    }

    private function getShiftAssignees($query, CarbonPeriod $period, $shift_type)
    {
        return $this->buildAssignmentBaseQuery($period, $query->pluck('id')->toArray())
            ->where($shift_type, 1)
            ->groupBy('business_member_id')
            ->pluck('business_member_id')
            ->toArray();
    }

    private function buildAssignmentBaseQuery(CarbonPeriod $period, $business_member_ids)
    {
        return $this->repo->builder()
            ->whereIn('business_member_id', $business_member_ids)
            ->whereBetween('date', [
                $period->getStartDate()->toDateString(),
                $period->getEndDate()->toDateString()
            ]);
    }

    private function mapBusinessMembersWithAssignments($business_members, CarbonPeriod $period)
    {
        $assignments = $this->getAssignments($period, $business_members);

        return $business_members->map(function ($business_member) use ($assignments, $period) {
            $business_member->shifts = $this->getSingleMemberAssignments($assignments, $business_member, $period);
            return $business_member;
        });
    }

    private function getAssignments(CarbonPeriod $period, $business_members)
    {
        return $this
            ->buildAssignmentBaseQuery($period, $business_members->pluck('id')->toArray())
            ->get()
            ->groupBy('business_member_id');
    }

    private function getSingleMemberAssignments($assignments, BusinessMember $business_member, CarbonPeriod $period)
    {
        $data = collect([]);

        $business_member_assignments = $assignments
            ->get($business_member->id)
            ->groupBy('date')
            ->map(function ($assignment) {
                return $assignment->first();
            });

        foreach ($period as $date) {
            $data->push($business_member_assignments->get($date->toDateString()));
        }

        return $data;
    }
}
