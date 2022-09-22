<?php namespace App\Sheba\Business\CoWorker;

use App\Models\Business;
use App\Models\BusinessDepartment;
use App\Models\BusinessRole;
use App\Models\Member;
use App\Models\Profile;
use Illuminate\Support\Collection;
use Sheba\Business\CoWorker\Statuses;
use Sheba\Repositories\Business\BusinessMemberRepository;

class ManagerSubordinateEmployeeList
{
    /*** @var BusinessMemberRepository $businessMemberRepository */
    private $businessMemberRepository;
    private $businessMembers;
    /** @var Business $business */
    private $business;

    public function __construct()
    {
        $this->businessMemberRepository = app(BusinessMemberRepository::class);
    }

    /**
     * @param $business_member
     * @param null $department
     * @param null $is_employee_active
     * @return array
     */
    public function get($business_member, $department = null, $is_employee_active = null): array
    {
        $managers = [];
        $this->getManager($business_member->id, $managers, $business_member->id);
        $managers_data = [];
        foreach ($managers as $manager) $managers_data[] = $this->formatSubordinateList($manager);
        if ($department) return $this->filterEmployeeByDepartment($business_member, $managers_data, $is_employee_active);
        return $managers_data;
    }

    /**
     * @param $business_member_id
     * @param $managers
     * @param $root_manager_id
     */
    public function getManager($business_member_id, &$managers, $root_manager_id)
    {
        $sub_ordinates = $this->getCoWorkersUnderSpecificManager($business_member_id);
        foreach ($sub_ordinates as $sub_ordinate) {
            if (array_key_exists($sub_ordinate->id, $managers) || $sub_ordinate->id == $root_manager_id) continue;
            $managers[$sub_ordinate->id] = $sub_ordinate;
            $this->getManager($sub_ordinate->id, $managers, $root_manager_id);
        }
    }

    /**
     * @param $business_member_id
     * @return Collection
     */
    private function getCoWorkersUnderSpecificManager($business_member_id): Collection
    {
        if ($this->business)
            return $this->businessMembers->where('manager_id', $business_member_id)->where('status', 'active');

        return $this->businessMemberRepository->where('manager_id', $business_member_id)->where('status', 'active')->get();
    }

    /**
     * @param $business_member
     * @param $managers_data
     * @param $is_employee_active
     * @return array
     */
    private function filterEmployeeByDepartment($business_member, $managers_data, $is_employee_active): array
    {
        $filtered_unique_managers_data = $this->removeSpecificBusinessMemberIdFormUniqueManagersData($business_member, $managers_data);

        $data = [];
        foreach ($filtered_unique_managers_data as $manager) {
            if ($is_employee_active && $manager['is_active'] == 0) continue;
            $data[$manager['department']][] = $manager;
        }
        return $data;
    }

    /**
     * @param  Business  $business
     * @return $this
     */
    public function setBusiness(Business $business): ManagerSubordinateEmployeeList
    {
        $this->business = $business;
        $this->businessMembers = $this->businessMemberRepository
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->select(["id", "business_id", "member_id", "employee_id", "manager_id", "status", "deleted_at"])->get();

        return $this;
    }

    /**
     * @param $managers_data
     * @return array
     */
    private function uniqueManagerData($managers_data): array
    {
        $out = [];
        foreach ($managers_data as $row) {
            $out[$row['id']] = $row;
        }
        return array_values($out);
    }

    /**
     * @param $business_member
     * @param $unique_managers_data
     * @return array
     */
    public function removeSpecificBusinessMemberIdFormUniqueManagersData($business_member, $unique_managers_data): array
    {
        return array_filter($unique_managers_data, function ($manager_data) use ($business_member) {
            return ($manager_data['id'] != $business_member->id);
        });
    }

    private function formatSubordinateList($business_member): array
    {
        /** @var Member $member */
        $member = $business_member->member;
        /** @var Profile $profile */
        $profile = $member->profile;
        /** @var BusinessRole $role */
        $role = $business_member->role;
        /** @var BusinessDepartment $department */
        $department = $role ? $role->businessDepartment : null;

        return [
            'id' => $business_member->id,
            'name' => $profile->name,
            'pro_pic' => $profile->pro_pic,
            'phone' => $business_member->mobile,
            'designation' => $role ? $role->name : null,
            'department_id' => $department ? $department->id : null,
            'department' => $department ? $department->name : null,
            'manager_id' => $business_member->manager_id,
            'is_active' => $business_member->status === Statuses::ACTIVE ? 1 : 0
        ];
    }
}
