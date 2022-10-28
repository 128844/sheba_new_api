<?php

namespace Sheba\Business\Employee;

use App\Models\Business;
use App\Models\BusinessMember;

class DashboardMenu
{
    private static $ALL_MENUS = [
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
    ];

    public function get(Business $business, BusinessMember $business_member)
    {
        $dashboard = collect(self::$ALL_MENUS);

        if (!$business->isPayrollEnabled()) $dashboard->forget("payslip");
        if (!$business->isVisitEnabled()) $dashboard->forget("visit");
        if (!$business->isManager($business_member)) $dashboard->forget("tracking")->forget("my_team");
        if (!$business->isLiveTrackEnabled()) $dashboard->forget("tracking");
        if (!$business->isShiftEnable()) $dashboard->forget("shift_calendar");

        return $dashboard->sortBy('order')->forgetEach('order')->values();
    }
}
