<?php namespace App\Sheba\Business\PayrollComponent\Components\Deductions;

use App\Models\Business;
use App\Models\BusinessMember;
use App\Sheba\Business\Attendance\AttendanceBasicInfo;
use App\Sheba\Business\PayrollSetting\PayrollCommonCalculation;
use App\Sheba\Business\PayrollSetting\PeriodWiseInformation;
use Carbon\Carbon;
use Sheba\Dal\Attendance\Contract as AttendanceRepositoryInterface;
use Sheba\Dal\OfficePolicy\Type;

class BusinessMemberPolicyRulesCalculator
{
    use AttendanceBasicInfo, PayrollCommonCalculation;

    /*** @var Business */
    private $business;
    /*** @var BusinessMember */
    private $businessMember;
    private $payrollSetting;
    private $businessPayDay;
    /*** @var PolicyActionTaker */
    private $policyActionTaker;
    private $additionBreakdown;
    private $timeFrame;
    /*** @var PeriodWiseInformation */
    private $periodWiseInformation;
    private $proratedTimeFrame;

    public function __construct()
    {
        $this->policyActionTaker = new PolicyActionTaker();
        $this->periodWiseInformation = new PeriodWiseInformation();
    }

    public function setBusiness(Business $business)
    {
        $this->business = $business;
        $this->payrollSetting = $this->business->payrollSetting;
        $this->businessPayDay = $this->payrollSetting->pay_day;
        return $this;
    }

    public function setBusinessMember(BusinessMember $business_member)
    {
        $this->businessMember = $business_member;
        return $this;
    }

    public function setTimeFrame($time_frame)
    {
        $this->timeFrame = $time_frame;
        return $this;
    }

    public function setProratedTimeFrame($prorated_time_frame)
    {
        $this->proratedTimeFrame = $prorated_time_frame;
        return $this;
    }

    public function setAdditionBreakdown($addition_breakdown)
    {
        $this->additionBreakdown = $addition_breakdown;
        return $this;
    }

    public function calculate()
    {
        $business_office = $this->business->officeHour;
        $is_grace_period_policy_enable = $business_office->is_grace_period_policy_enable;
        $is_late_checkin_early_checkout_policy_enable = $business_office->is_late_checkin_early_checkout_policy_enable;
        $is_for_late_checkin = $business_office->is_for_late_checkin;
        $is_for_early_checkout = $business_office->is_for_early_checkout;
        $is_unpaid_leave_policy_enable = $business_office->is_unpaid_leave_policy_enable;
        $time_frame = $this->proratedTimeFrame ? $this->proratedTimeFrame : $this->timeFrame;
        $business_member_leave = $this->businessMember->leaves()->accepted()->between($time_frame)->get();
        list($leaves, $leaves_date_with_half_and_full_day) = $this->formatLeaveAsDateArray($business_member_leave);
        $period = $this->createPeriodByTime($time_frame->start, $this->timeFrame->end);
        $total_policy_working_days = $this->getTotalBusinessWorkingDays($this->createPeriodByTime($this->timeFrame->start, $this->timeFrame->end), $business_office);
        $period_wise_information = $this->periodWiseInformation
            ->setPeriod($period)
            ->setBusinessOffice($business_office)
            ->setBusinessMemberLeave($leaves)
            ->setBusinessMember($this->businessMember)
            ->setIsCalculateAttendanceInfo(1)
            ->get();
        $late_checkin_early_checkout_days = 0;
        if ($is_for_late_checkin && $is_for_early_checkout) $late_checkin_early_checkout_days = $period_wise_information->total_late_checkin_or_early_checkout;
        elseif ($is_for_late_checkin && !$is_for_early_checkout) $late_checkin_early_checkout_days = $period_wise_information->total_late_checkin;
        elseif (!$is_for_late_checkin && $is_for_early_checkout) $late_checkin_early_checkout_days = $period_wise_information->total_early_checkout;
        $total_absent = ($period_wise_information->total_working_days - $period_wise_information->total_present);
        $attendance_adjustment = 0;
        $leave_adjustment = 0;
        $this->policyActionTaker
            ->setBusiness($this->business)
            ->setBusinessMember($this->businessMember)
            ->setPayrollSetting($this->payrollSetting)
            ->setAdditionBreakdown($this->additionBreakdown)
            ->setTotalWorkingDays($total_policy_working_days);
        if ($is_grace_period_policy_enable) $attendance_adjustment += $this->policyActionTaker->setPolicyType(Type::GRACE_PERIOD)->setPenaltyDays($period_wise_information->grace_time_over)->takeAction();
        if ($is_late_checkin_early_checkout_policy_enable) $attendance_adjustment += $this->policyActionTaker->setPolicyType(Type::LATE_CHECKIN_EARLY_CHECKOUT)->setPenaltyDays($late_checkin_early_checkout_days)->takeAction();
        $leave_adjustment += $this->policyActionTaker->setPolicyType(Type::UNPAID_LEAVE)->setPenaltyDays($total_absent)->setIsUnpaidLeaveEnable($is_unpaid_leave_policy_enable)->takeAction();
        return ['attendance_adjustment' => floatValFormat($attendance_adjustment), 'leave_adjustment' => floatValFormat($leave_adjustment), 'tax' => 0];
    }
}
