<?php

namespace App\Sheba\Business\Prorate;

use Carbon\Carbon;
use Sheba\Business\Prorate\Creator as ProrateCreator;
use Sheba\Business\Prorate\Requester as ProrateRequester;
use Sheba\Business\Prorate\Updater as ProrateUpdater;
use Sheba\Dal\BusinessMemberLeaveType\Contract as BusinessMemberLeaveTypeInterface;

class CalculateProrate
{
    const AUTO_PRORATE_NOTE = "Leave Auto prorated based on employee joining date";
    private $businessMember;
    private $business;
    private $fiscalYear;
    private $leaveType;
    /** @var ProrateRequester $prorateRequester */
    private $prorateRequester;
    /** @var ProrateCreator $prorateCreator */
    private $prorateCreator;
    private $prorateupdater;
    private $businessMemberLeaveTypeRepo;

    public function __construct()
    {
        $this->prorateRequester = app(ProrateRequester::class);
        $this->prorateCreator = app(ProrateCreator::class);
        $this->prorateupdater = app(ProrateUpdater::class);
        $this->businessMemberLeaveTypeRepo = app(BusinessMemberLeaveTypeInterface::class);
    }

    public function setBusiness($business)
    {
        $this->business = $business;
        return $this;
    }

    public function setBusinessFiscalYear($business_fiscal_year)
    {
        $this->fiscalYear = $business_fiscal_year;
        return $this;
    }

    public function setLeaveType($leave_type)
    {
        $this->leaveType = $leave_type;
        return $this;
    }

    public function setBusinessMember($business_member)
    {
        $this->businessMember = $business_member;
        return $this;
    }

    public function calculate()
    {
        $joining_date = Carbon::parse($this->businessMember->join_date);
        if ($joining_date->format('Y-m') <= $this->fiscalYear->start->format('Y-m')) return false;
        $remaining_month = Carbon::now()->diffInMonths($this->fiscalYear->end);
        $total_leave_days = $this->leaveType->total_days;
        $prorated_days = intval(($remaining_month / 12) * $total_leave_days);
        if ($prorated_days > 0) $this->createBusinessMemberLeaveProrate($prorated_days);
    }

    private function createBusinessMemberLeaveProrate($prorated_days)
    {
        $business_member_id = $this->businessMember->id;
        $leave_type_id = $this->leaveType->id;
        $business_member_leave_type = $this->businessMemberLeaveTypeRepo->where('business_member_id', $business_member_id)->where('leave_type_id', $leave_type_id)->first();
        if ((isset($business_member_leave_type) && $business_member_leave_type->total_days == $prorated_days)) return false;
        $this->prorateRequester->setTotalDays($prorated_days)
            ->setLeaveTypeId($leave_type_id)
            ->setNote(self::AUTO_PRORATE_NOTE);
        if (!$business_member_leave_type) {
            $this->prorateRequester->setBusinessMemberIds([$business_member_id]);
            $this->prorateCreator->setRequester($this->prorateRequester)->create();
        } else {
            $this->prorateupdater->setRequester($this->prorateRequester)->setBusinessMemberLeaveType($business_member_leave_type)->update();
        }
    }


}