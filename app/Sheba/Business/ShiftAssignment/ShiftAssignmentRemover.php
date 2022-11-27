<?php namespace App\Sheba\Business\ShiftAssignment;

use Sheba\Dal\ShiftAssignment\ShiftAssignmentRepository;

class ShiftAssignmentRemover
{
    /*** @var ShiftAssignmentRepository */
    private $shiftAssignmentRepository;

    public function __construct(ShiftAssignmentRepository $shiftAssignmentRepository)
    {
        $this->shiftAssignmentRepository = $shiftAssignmentRepository;
    }

    public function deleteAllAfterToday(array $business_member_ids)
    {
        $this->shiftAssignmentRepository->deleteAllAfterToday($business_member_ids);
    }
}
