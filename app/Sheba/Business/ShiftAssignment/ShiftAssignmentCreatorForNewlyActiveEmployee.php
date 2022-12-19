<?php namespace Sheba\Business\ShiftAssignment;

use App\Models\Business;
use App\Models\BusinessMember;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Sheba\Business\Shift\ShiftAssignmentRequest;
use Sheba\Dal\ShiftAssignment\ShiftAssignmentRepository;

class ShiftAssignmentCreatorForNewlyActiveEmployee
{
    /** @var ShiftAssignmentRepository */
    private $shiftAssignmentRepo;

    public function __construct(ShiftAssignmentRepository $shift_assignment_repo)
    {
        $this->shiftAssignmentRepo = $shift_assignment_repo;
    }

    public function handle(Business $business, BusinessMember $business_member)
    {
        if (!$business->isShiftEnabled()) return;
        if (!$business_member->isBusinessMemberActive()) return;
        if ($this->isShiftAssignmentExist($business_member)) return;

        $assignments = [];

        $period = CarbonPeriod::create(Carbon::now()->subDays(2), Carbon::now()->addMonths(3));

        foreach ($period as $date) {
            $assignments[] = (new ShiftAssignmentRequest($business_member, $date))->activateGeneral();
        }

        $this->shiftAssignmentRepo->insertWithRequest($assignments);
    }

    private function isShiftAssignmentExist(BusinessMember $business_member)
    {
        return $this->shiftAssignmentRepo->where('business_member_id', $business_member->id)->count();
    }
}
