<?php namespace Sheba\Business\ApprovalRequest;

use App\Jobs\Business\SendLeavePushNotificationToEmployee;
use App\Jobs\Business\SendNotificationToApprover;
use Sheba\Dal\ApprovalRequest\Model as ApprovalRequest;
use App\Models\Profile;
use Exception;

class Notification
{
    /**
     * @param ApprovalRequest $approval_request
     * @param Profile $profile
     */
    public function sendPushToApprover(ApprovalRequest $approval_request, Profile $profile)
    {
        $leave_applicant = $profile->name ? $profile->name : 'n/s';
        dispatch(new SendLeavePushNotificationToEmployee($approval_request, $leave_applicant));
    }

    /**
     * @param ApprovalRequest $approval_request
     * @param Profile $profile
     * @throws Exception
     */
    public function sendShebaNotificationToApprover(ApprovalRequest $approval_request, Profile $profile)
    {
        $business_member = $approval_request->approver;
        $member = $business_member->member;
        $leave_applicant = $profile->name ? $profile->name : 'n/s';

        $title = "$leave_applicant requested for a leave";
        notify()->member($member)->send([
            'title' => $title,
            'type' => 'Info',
            'event_type' => get_class($approval_request),
            'event_id' => $approval_request->id
        ]);
        #dispatch(new SendNotificationToApprover($approval_request, $profile));
    }
}