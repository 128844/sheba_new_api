<?php namespace Sheba\Business\Shift;

use App\Models\BusinessMember;
use Carbon\Carbon;

class ShiftAssignmentRequest
{
    /** @var BusinessMember */
    private $businessMember;
    /** @var Carbon */
    private $date;
    /** @var bool */
    private $isHalfDayActivated = false;
    /** @var bool */
    private $isGeneralActivated = false;
    /** @var bool */
    private $isUnassignedActivated = false;
    /** @var bool */
    private $isShiftActivated = false;

    public function __construct(BusinessMember $business_member, Carbon $date)
    {
        $this->businessMember = $business_member;
        $this->date = $date;
    }

    public function getBusinessMemberId(): int
    {
        return $this->businessMember->id;
    }

    public function getDate()
    {
        return $this->date->toDateString();
    }

    public function activateHalfDay()
    {
        $this->isHalfDayActivated = true;
        return $this;
    }

    public function getIsHalfDayActivated(): int
    {
        return $this->isHalfDayActivated ? 1 : 0;
    }

    public function activateGeneral()
    {
        $this->isGeneralActivated = true;
        return $this;
    }

    public function getIsGeneralActivated(): int
    {
        return $this->isGeneralActivated ? 1 : 0;
    }

    public function unassign()
    {
        $this->isUnassignedActivated = true;
        return $this;
    }

    public function getIsUnassignedActivated(): int
    {
        return $this->isUnassignedActivated ? 1 : 0;
    }

    public function activateShift()
    {
        $this->isShiftActivated = true;
        return $this;
    }

    public function getIsShiftActivated(): int
    {
        return $this->isShiftActivated ? 1 : 0;
    }
}
