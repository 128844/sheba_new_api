<?php namespace App\Sheba\Business\PayrollComponent\Components\Deductions;

use App\Models\Business;
use App\Models\BusinessMember;
use App\Sheba\Business\PayrollSetting\PayrollCommonCalculation;
use App\Sheba\Business\PayrollSetting\PayrollConstGetter;
use Sheba\Business\Prorate\Creator as ProrateCreator;
use Sheba\Business\Prorate\Requester as ProrateRequester;
use Sheba\Business\Prorate\Updater as ProrateUpdater;
use Sheba\Dal\BusinessMemberLeaveType\Contract as BusinessMemberLeaveTypeInterface;
use Sheba\Dal\OfficePolicy\Type;
use Sheba\Dal\OfficePolicyRule\ActionType;
use App\Sheba\Business\LeaveProrateLogs\Creator as LeaveProrateLogCreator;

class PolicyActionTaker
{
    use PayrollCommonCalculation;

    const LEAVE_PRORATE_TYPE = 'leave_policy';

    /*** @var Business */
    private $business;
    private $penaltyDays;
    private $totalWorkingDays;
    private $policyRules;
    /*** @var BusinessMember */
    private $businessMember;
    private $businessMemberLeaveTypeRepo;
    /*** @var ProrateRequester $prorateRequester */
    private $prorateRequester;
    /*** @var ProrateCreator $prorateCreator */
    private $prorateCreator;
    /*** @var ProrateUpdater $prorateUpdater */
    private $prorateUpdater;
    private $payrollSetting;
    private $additionBreakdown;
    private $policyType;
    private $isUnpaidLeavePolicyEnable;
    private $businessMemberSalary;
    private $businessOfficeHour;
    /*** @var LeaveProrateLogCreator $leaveProrateLogCreator */
    private $leaveProrateLogCreator;

    public function __construct()
    {
        $this->businessMemberLeaveTypeRepo = app(BusinessMemberLeaveTypeInterface::class);
        $this->prorateRequester = app(ProrateRequester::class);
        $this->prorateCreator = app(ProrateCreator::class);
        $this->prorateUpdater = app(ProrateUpdater::class);
        $this->leaveProrateLogCreator = app(LeaveProrateLogCreator::class);
    }

    public function setBusiness(Business $business)
    {
        $this->business = $business;
        $this->businessOfficeHour = $this->business->officeHour;
        return $this;
    }

    public function setBusinessMember(BusinessMember $business_member)
    {
        $this->businessMember = $business_member;
        $this->businessMemberSalary = $business_member->salary ? floatValFormat($this->businessMember->salary->gross_salary) : 0;
        return $this;
    }

    public function setPayrollSetting($payroll_setting)
    {
        $this->payrollSetting = $payroll_setting;
        return $this;
    }

    public function setPolicyType($policy_type)
    {
        $this->policyType = $policy_type;
        return $this;
    }

    public function setAdditionBreakdown($addition_breakdown)
    {
        $this->additionBreakdown = $addition_breakdown;
        return $this;
    }

    public function setPenaltyDays($penalty_days)
    {
        $this->penaltyDays = $penalty_days;
        return $this;
    }

    public function setTotalWorkingDays($total_working_days)
    {
        $this->totalWorkingDays = $total_working_days;
        return $this;
    }

    public function setIsUnpaidLeaveEnable($is_unpaid_leave_policy_enable)
    {
        $this->isUnpaidLeavePolicyEnable = $is_unpaid_leave_policy_enable;
        return $this;
    }

    public function takeAction()
    {
        if ($this->policyType === Type::UNPAID_LEAVE && !$this->isUnpaidLeavePolicyEnable) {
            $component = $this->businessOfficeHour->unauthorised_leave_penalty_component;
            $one_working_day_amount = $this->getOneWorkingDayAmountForGrossComponent($this->payrollSetting, $this->businessMember, $component);
            return $this->totalPenaltyAmountByOneWorkingDay($one_working_day_amount, $this->penaltyDays);
        }

        $this->policyRules = $this->business->policy()->where('policy_type', $this->policyType)->where(function ($query) {
            $query->where('from_days', '<=', $this->penaltyDays);
            $query->where('to_days', '>=', $this->penaltyDays);
        })->first();

        if (!$this->policyRules && $this->policyType == Type::LATE_CHECKIN_EARLY_CHECKOUT) {
            $policyRules = $this->business->policy()->where('policy_type', $this->policyType)->last();
            if ($this->penaltyDays >= $policyRules->to_days) $this->policyRules = $policyRules;
        }
        return $this->rulesPolicyCalculation();
    }

    private function rulesPolicyCalculation()
    {

        $policy_total = 0;
        if (!$this->policyRules) return $policy_total;
        $action = $this->policyRules->action;
        if ($action === ActionType::NO_PENALTY) return $policy_total;
        if ($action === ActionType::CASH_PENALTY) return floatValFormat($this->policyRules->penalty_amount);
        $business_member_salary = $this->businessMember->salary ? floatValFormat($this->businessMember->salary->gross_salary) : 0;
        if ($action === ActionType::LEAVE_ADJUSTMENT) {
            $penalty_type = intval($this->policyRules->penalty_type);
            $penalty_amount = floatValFormat($this->policyRules->penalty_amount);
            $existing_prorate = $this->businessMemberLeaveTypeRepo->where('business_member_id', $this->businessMember->id)->where('leave_type_id', $penalty_type)->first();
            $leave_type = $this->business->leaveTypes->find($penalty_type);
            $total_leave_type_days = $leave_type ? $leave_type->total_days : 0;
            $total_leave_type_days = $existing_prorate ? floatValFormat($existing_prorate->total_days) : floatValFormat($total_leave_type_days);
            if ($total_leave_type_days < $penalty_amount) {
                $one_working_day_amount = $this->oneWorkingDayAmount($business_member_salary, floatValFormat($this->totalWorkingDays));
                return $this->totalPenaltyAmountByOneWorkingDay($one_working_day_amount, $penalty_amount);
            }
            $previous_leave_type_total_days = $existing_prorate ? $existing_prorate->total_days : $leave_type->total_days;
            $total_leave_type_days_after_penalty = $total_leave_type_days - $penalty_amount;
            $this->prorateRequester->setBusinessMemberIds([$this->businessMember->id])
                ->setLeaveTypeId($penalty_type)
                ->setTotalDays($total_leave_type_days_after_penalty)
                ->setNote(PayrollConstGetter::LEAVE_PRORATE_NOTE_FOR_POLICY_ACTION);
            $this->leaveProrateLogCreator->setBusinessMember($this->businessMember)->setProratedType(self::LEAVE_PRORATE_TYPE)->setProratedLeaveDays($total_leave_type_days_after_penalty)->setPreviousLeaveTypeTotalDays($previous_leave_type_total_days);
            if ($existing_prorate) {
                $this->prorateUpdater->setRequester($this->prorateRequester)->setBusinessMemberLeaveType($existing_prorate)->update();
                $this->leaveProrateLogCreator->setLeaveType($existing_prorate)->setLeaveTypeTarget(get_class($existing_prorate));
            }
            else {
                $this->prorateCreator->setRequester($this->prorateRequester)->create();
                $this->leaveProrateLogCreator->setLeaveType($leave_type)->setLeaveTypeTarget(get_class($leave_type));
            }
            $this->leaveProrateLogCreator->create();
            return $policy_total;
        }
        if ($action === ActionType::SALARY_ADJUSTMENT) {
            $penalty_type = $this->policyRules->penalty_type;
            $penalty_amount = floatValFormat($this->policyRules->penalty_amount);
            $one_working_day_amount_for_gross = $this->getOneWorkingDayAmountForGrossComponent($this->payrollSetting, $this->businessMember, $penalty_type);
            if ($one_working_day_amount_for_gross !== null) return $this->totalPenaltyAmountByOneWorkingDay($one_working_day_amount_for_gross, $penalty_amount);
            $one_working_day_amount_for_addition = $this->getOneWorkingDayAmountForAdditionComponent($this->payrollSetting, $this->additionBreakdown, $penalty_type);
            if ($one_working_day_amount_for_addition !== null) return ($one_working_day_amount_for_addition * $penalty_amount);
            $one_working_day_amount = $this->oneWorkingDayAmount($business_member_salary,  floatValFormat($this->totalWorkingDays));
            return $this->totalPenaltyAmountByOneWorkingDay($one_working_day_amount, $penalty_amount);
        }
        return $policy_total;
    }
}
