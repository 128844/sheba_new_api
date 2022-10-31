<?php namespace Sheba\Business\ShiftAssignment;

use App\Models\BusinessMember;
use Carbon\Carbon;
use Sheba\Dal\BusinessOfficeHours\Contract as BusinessOfficeHours;
use Sheba\Dal\ShiftAssignment\ShiftAssignmentRepository;
use Sheba\Dal\ShiftAssignment\ShiftAssignment;

class ShiftAssignmentFinder
{
    const MAX_MINUTES_GAP = 16 * 60;

    /** * @var BusinessMember */
    private $businessMember;
    /*** @var ShiftAssignmentRepository */
    private $shiftAssignmentRepo;
    /*** @var BusinessOfficeHours */
    private $businessOfficeHoursRepo;

    public function __construct(ShiftAssignmentRepository $shift_assignment_repo, BusinessOfficeHours $business_office_hours_repo)
    {
        $this->shiftAssignmentRepo = $shift_assignment_repo;
        $this->businessOfficeHoursRepo = $business_office_hours_repo;
    }

    public function setBusinessMember(BusinessMember $business_member): ShiftAssignmentFinder
    {
        $this->businessMember = $business_member;
        return $this;
    }

    public function findCurrentAssignment(): ShiftAssignment
    {
        list($yesterday_assignment, $today_assignment, $tomorrow_assignment) = $this->shiftAssignmentRepo->shiftAssignmentFromYesterdayToTomorrow($this->businessMember->id);
        if ($this->isTodayInGeneral($today_assignment, $yesterday_assignment, $tomorrow_assignment)) return $today_assignment;

        $avg_minutes_diff_of_today_yesterday =  intval($this->getMinutesGapOfTwoAssignments($yesterday_assignment, $today_assignment) / 2);

        if ($yesterday_assignment->getEndTime()->addMinutes($avg_minutes_diff_of_today_yesterday)->gt(Carbon::now())) return $yesterday_assignment;

        $avg_minutes_diff_of_today_tomorrow =  intval($this->getMinutesGapOfTwoAssignments($today_assignment, $tomorrow_assignment) /2);

        if ($tomorrow_assignment->getStartTime()->subMinutes($avg_minutes_diff_of_today_tomorrow)->lt(Carbon::now())) return $tomorrow_assignment;

        return $today_assignment;
    }

    private function getMinutesGapOfTwoAssignments(ShiftAssignment $earlier_assignment, ShiftAssignment $later_assignment): int
    {
        if ($earlier_assignment->isUnassigned() || $later_assignment->isUnassigned()) return self::MAX_MINUTES_GAP;

        $business_office_hours = $this->businessOfficeHoursRepo->getOfficeTime($this->businessMember->business);

        $assignment_1_end_time = $earlier_assignment->isInShift() ? $earlier_assignment->getEndTime() : $business_office_hours->getEndTimeOfDate($earlier_assignment->getDate());
        $assignment_2_start_time = $later_assignment->isInShift() ? $later_assignment->getStartTime() : $business_office_hours->getStartTimeOfDate($later_assignment->getDate());

        return min($assignment_1_end_time->diffInMinutes($assignment_2_start_time), self::MAX_MINUTES_GAP);
    }

    private function isTodayInGeneral(ShiftAssignment $today_assignment, ShiftAssignment $yesterday_assignment, ShiftAssignment $tomorrow_assignment): bool
    {
        return $today_assignment->isGeneral()
            && ($yesterday_assignment->isGeneral() || $yesterday_assignment->isUnassigned())
            && ($tomorrow_assignment->isGeneral() || $tomorrow_assignment->isUnassigned());
    }
}
