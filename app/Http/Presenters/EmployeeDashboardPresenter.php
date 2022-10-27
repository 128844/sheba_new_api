<?php namespace App\Http\Presenters;

use App\Models\Business;
use Sheba\Business\Employee\EmployeeDashboard;
use Sheba\Dal\LiveTrackingSettings\LiveTrackingSettings;

class EmployeeDashboardPresenter extends Presenter
{
    /*** @var EmployeeDashboard */
    private $employeeDashboard;
    private $businessMember;
    /** @var Business */
    private $business;
    private $member;

    public function __construct(EmployeeDashboard $employee_dashboard)
    {
        $this->employeeDashboard = $employee_dashboard;
        $this->businessMember = $this->employeeDashboard->getBusinessMember();
        $this->business = $this->businessMember->business;
        $this->member = $this->businessMember->member;
    }

    public function toArray()
    {
        $department = $this->businessMember->department();
        $profile = $this->businessMember->profile();
        $designation = $this->businessMember->role()->first();
        $is_manager = (int)$this->businessMember->isManager();

        /** @var  LiveTrackingSettings $live_tracking_settings */
        $live_tracking_settings = $this->business->liveTrackingSettings;
        $pending_visit_count = $this->employeeDashboard->getPendingVisitCount();
        $single_pending_visit = $this->employeeDashboard->getSinglePendingVisit();
        $current_visit = $this->employeeDashboard->getCurrentVisit();
        $note_action = $this->employeeDashboard->getNoteAction();
        return [
            'info' => [
                'id' => $this->member->id,
                'business_member_id' => $this->businessMember->id,
                'department_id' => $department ? $department->id : null,
                'notification_count' => $this->member->notifications()->unSeen()->count(),
                'attendance' => [
                    'can_checkin' => (int) $this->employeeDashboard->canCheckIn(),
                    'can_checkout' => (int) $this->employeeDashboard->canCheckOut(),
                    'shift' => $this->getShiftAssignmentDetails()
                ],
                'note_data' => [
                    'date' => $this->employeeDashboard->hasLastAttendance() ? $this->employeeDashboard->getLastAttendanceDate()->format('jS F Y') : null,
                    'is_note_required' => is_null($note_action) ? 0 : 1,
                    'note_action' => $note_action
                ],
                'is_remote_enable' => $this->business->isRemoteAttendanceEnable($this->businessMember->id),
                'is_approval_request_required' => $this->employeeDashboard->isApprovalRequestRequired() ? 1 : 0,
                'approval_requests' => ['pending_request' => $this->employeeDashboard->getPendingApprovalRequestCount()],
                'is_profile_complete' => $this->employeeDashboard->getProfileCompletionScore() ? 1 : 0,
                'is_eligible_for_lunch' => $this->business->isEligibleForLunch() ? [
                    'link' => config('b2b.BUSINESSES_LUNCH_LINK'),
                ] : null,
                'is_sheba_platform' => $this->business->isShebaPlatform() ? 1 : 0,
                'is_payroll_enable' => $this->business->payrollSetting->is_enable,
                'is_enable_employee_visit' => $this->business->is_enable_employee_visit,
                'pending_visit_count' => $pending_visit_count,
                'today_visit_count' => $this->employeeDashboard->getTodayVisitCount(),
                'single_visit' => $pending_visit_count === 1 ? [
                    'id' => $single_pending_visit->id,
                    'title' => $single_pending_visit->title
                ] : null,
                'currently_on_visit' => $current_visit ? $current_visit->id : null,
                'is_badge_seen' => (int)$this->employeeDashboard->isBadgeSeen(),
                'is_manager' => $is_manager,
                'user_profile' => [
                    'name' => $profile->name ?: null,
                    'pro_pic' => $profile->pro_pic ?: null,
                    'designation' => $designation ? ucwords($designation->name) : null
                ],
                'is_live_track_enable' => $this->businessMember->is_live_track_enable,
                'location_fetch_interval_in_minutes' => $live_tracking_settings ? $live_tracking_settings->location_fetch_interval_in_minutes : null
            ]
        ];
    }

    private function getShiftAssignmentDetails()
    {
        $current_assignment = $this->employeeDashboard->getCurrentAssignment();
        if (!$current_assignment) return null;
        return [
            'id' => $current_assignment->id,
            'title' => $current_assignment->shift_title,
            'start_time' => $current_assignment->start_time,
            'end_time' => $current_assignment->end_time,
        ];
    }

}
