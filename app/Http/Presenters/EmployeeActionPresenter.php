<?php namespace App\Http\Presenters;

use Carbon\Carbon;
use Sheba\Business\Employee\AttendanceActionChecker;

class EmployeeActionPresenter extends Presenter
{
    /*** @var AttendanceActionChecker */
    private $attendanceActionChecker;

    public function __construct(AttendanceActionChecker $attendance_action_checker)
    {
        $this->attendanceActionChecker = $attendance_action_checker;
        return $this;
    }

    public function toArray()
    {
        $is_remote_enable = $this->attendanceActionChecker->isRemoteAttendanceEnable();
        return [
            'can_checkin' => (int) $this->attendanceActionChecker->canCheckIn(),
            'can_checkout' => (int) $this->attendanceActionChecker->canCheckOut(),
            'checkin_time' => $this->actionTimeFormatter($this->attendanceActionChecker->getCheckInTime()),
            'checkout_time' => $this->actionTimeFormatter($this->attendanceActionChecker->getCheckOutTime()),
            'is_geo_required' => $is_remote_enable ? 1 : 0,
            'is_remote_enable' => $is_remote_enable,
            'is_geo_location_enable' => $this->attendanceActionChecker->isGeoLocationAttendanceEnable(),
            'is_live_track_enable' => (int) $this->attendanceActionChecker->isLiveTrackEnable(),
            'shift' => $this->getShiftAssignmentDetails()
        ];
    }

    private function getShiftAssignmentDetails()
    {
        $current_assignment = $this->attendanceActionChecker->getCurrentAssignment();
        if (!$current_assignment) return null;
        return [
            'id' => $current_assignment->id,
            'title' => $current_assignment->shift_title,
            'start_time' => $current_assignment->start_time,
            'end_time' => $current_assignment->end_time,
        ];
    }

    private function actionTimeFormatter($action_time)
    {
        if (!$action_time) return null;
        //return Carbon::parse($action_time)->format('h:i:s');
        return $action_time;
    }
}
