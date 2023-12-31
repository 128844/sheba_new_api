<?php namespace Sheba\Business\CoWorker\Filter;

use Illuminate\Support\Collection;
use Illuminate\Http\Request;

class CoWorkerInfoFilter
{
    /**
     * @param $business_members
     * @param Request $request
     * @return mixed
     */
    public function filterByDepartment($business_members, Request $request)
    {
        return $business_members->whereHas('role', function ($q) use ($request) {
            $q->whereHas('businessDepartment', function ($q) use ($request) {
                $q->where('business_departments.id', $request->department);
            });
        });
    }

    /**
     * @param $business_members
     * @param Request $request
     * @return mixed
     */
    public function filterByStatus($business_members, Request $request)
    {
        return $business_members->where('status', $request->status);
    }

    public function filterCoworkerInList($employees, Request $request)
    {
        if ($request->has('search')) $employees = $this->searchEmployee($employees, $request);
        if ($request->has('employee_type')) $employees = $this->filterByEmployeeType($employees, $request)->values();
        return $employees;
    }

    /**
     * @param $employees
     * @param Request $request
     * @return mixed
     */
    private function searchEmployee($employees, Request $request)
    {
        $employees = $employees->toArray();
        $employee_ids = array_filter($employees, function ($employee) use ($request) {
            return str_contains($employee['employee_id'], $request->search);
        });
        $employee_names = array_filter($employees, function ($employee) use ($request) {
            return str_contains(strtoupper($employee['profile']['name']), strtoupper($request->search));
        });
        $employee_emails = array_filter($employees, function ($employee) use ($request) {
            return str_contains(strtoupper($employee['profile']['email']), strtoupper($request->search));
        });
        $employee_mobiles = array_filter($employees, function ($employee) use ($request) {
            return str_contains($employee['profile']['mobile'], formatMobile($request->search));
        });

        $searched_employees = collect(array_merge($employee_ids, $employee_names, $employee_emails, $employee_mobiles));
        $searched_employees = $searched_employees->unique(function ($employee) {
            return $employee['id'];
        });
        return $searched_employees->values()->all();
    }

    /**
     * @param $employees
     * @param Request $request
     * @return Collection
     */
    private function filterByEmployeeType($employees, Request $request)
    {
        $is_super = $request->employee_type === 'super_admin' ? 1 : 0;
        return collect($employees)->filter(function ($employee) use ($is_super) {
            return $employee['is_super'] == $is_super;
        });
    }
}