<?php

namespace Sheba\Business\Employee;

use App\Models\Business;
use App\Models\BusinessMember;
use Sheba\Dal\LiveTrackingSettings\LiveTrackingSettings;
use Sheba\Dal\PayrollSetting\PayrollSetting;

class DashboardMenu
{
    public function get(Business $business, BusinessMember $business_member)
    {
        /** @var PayrollSetting $payroll_setting */
        $payroll_setting = $business->payrollSetting;
        /** @var  LiveTrackingSettings $live_tracking_settings */
        $live_tracking_settings = $business->liveTrackingSettings;

        $is_enable_employee_visit = $business->is_enable_employee_visit;

        $is_manager = $business->getActiveBusinessMember()->where('manager_id', $business_member->id)->count() > 0 ? 1 : 0;

        $dashboard = $this->getAllMenus();

        if (!$payroll_setting->is_enable) $dashboard->forget("payslip");
        if (!$is_enable_employee_visit) $dashboard->forget("visit");
        if (!$this->isLiveTrackingEnable($live_tracking_settings) || !$is_manager) $dashboard->forget("tracking");
        if (!$is_manager) $dashboard->forget("my_team");
        if (!$business->isShiftEnable()) $dashboard->forget("shift_calendar");

        return $dashboard->sortBy('order')->except('order')->values();
    }


    /**
     * @param $live_tracking_settings
     * @return bool
     */
    private function isLiveTrackingEnable($live_tracking_settings)
    {
        if (!$live_tracking_settings) {
            return false;
        } elseif ($live_tracking_settings->is_enable) {
            return true;
        }
        return false;
    }

    private function getAllMenus()
    {
        return collect([
            "support" => [
                'title' => 'Support',
                'target_type' => 'support',
                "order" => 1
            ],
            "attendance" => [
                'title' => 'Attendance',
                'target_type' => 'attendance',
                "order" => 2
            ],
            "shift_calendar" => [
                'title' => 'Shift Calendar',
                'target_type' => 'shift_calendar',
                "order" => 3
            ],
            "notice" => [
                'title' => 'Notice',
                'target_type' => 'notice',
                "order" => 4
            ],
            "expense" => [
                'title' => 'Expense',
                'target_type' => 'expense',
                "order" => 5
            ],
            "leave" => [
                'title' => 'Leave',
                'target_type' => 'leave',
                "order" => 6
            ],
            "approval" => [
                'title' => 'Approval',
                'target_type' => 'approval',
                "order" => 7
            ],
            "phonebook" => [
                'title' => 'Phonebook',
                'target_type' => 'phonebook',
                "order" => 8
            ],
            "payslip" => [
                'title' => 'Payslip',
                'target_type' => 'payslip',
                "order" => 9
            ],
            "visit" => [
                'title' => 'Visit',
                'target_type' => 'visit',
                "order" => 10
            ],
            "tracking" => [
                'title' => 'Tracking',
                'target_type' => 'tracking',
                "order" => 11
            ],
            "my_team" => [
                'title' => 'My Team',
                'target_type' => 'my_team',
                "order" => 12
            ],
            "feedback" => [
                'title' => 'Feedback',
                'target_type' => 'feedback',
                'link' => "https://sheba.freshdesk.com/support/tickets/new",
                "order" => 13
            ],
        ]);
    }
}
