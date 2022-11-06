<?php namespace App\Sheba\Business\CoWorker\ProfileInformation;


use App\Models\BusinessMember;
use App\Sheba\Business\CoWorker\GetBusinessRole;
use Sheba\Business\ShiftAssignment\ShiftAssignmentCreatorForNewlyActiveEmployee;
use Sheba\Repositories\Interfaces\BusinessMemberRepositoryInterface;
use Sheba\Repositories\Interfaces\ProfileRepositoryInterface;
use DB;

class ProfileUpdater
{
    /** @var ProfileRequester $profile_requester*/
    private $profileRequester;
    /** @var ProfileRepositoryInterface $profileRepository*/
    private $profileRepository;
    /*** @var BusinessMemberRepositoryInterface */
    private $businessMemberRepository;
    /*** @var ShiftAssignmentCreatorForNewlyActiveEmployee */
    private $newlyActiveEmployeeShiftAssignment;

    public function __construct(ShiftAssignmentCreatorForNewlyActiveEmployee $shift_assignment)
    {
        $this->profileRepository = app(ProfileRepositoryInterface::class);
        $this->businessMemberRepository = app(BusinessMemberRepositoryInterface::class);
        $this->newlyActiveEmployeeShiftAssignment = $shift_assignment;
    }

    public function setProfileRequester(ProfileRequester $profile_requester)
    {
        $this->profileRequester = $profile_requester;
        return $this;
    }

    public function update()
    {
        DB::transaction(function () {
            $this->makeData();
        });
    }

    private function makeData()
    {
        $business_member = $this->profileRequester->getBusinessMember();
        $profile = $business_member->member->profile;
        $profile_data = $this->updateProfile();
        $business_member_data = $this->updateBusinessMember();
        $this->profileRepository->updateRaw($profile, $profile_data);
        $this->businessMemberRepository->update($business_member, $business_member_data);
        $this->createShiftAssignment($business_member);
    }

    private function getBusinessRole($department, $designation)
    {
        return (new GetBusinessRole($department, $designation))->get();
    }

    private function updateProfile()
    {
        return [
            'name' => $this->profileRequester->getName(),
            'gender' => $this->profileRequester->getGender()
        ];
    }

    private function updateBusinessMember()
    {
        $designation = $this->profileRequester->getDesignation();
        $department = $this->profileRequester->getDepartment();
        $business_role = $this->getBusinessRole($department, $designation);
        return [
            'business_role_id' => $business_role->id,
            'join_date' => $this->profileRequester->getJoiningDate(),
            'status' => 'active'
        ];
    }

    private function createShiftAssignment(BusinessMember $business_member)
    {
        $this->newlyActiveEmployeeShiftAssignment->handle($business_member->business, $business_member);
    }

}
