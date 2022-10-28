<?php namespace App\Http\Controllers\Employee;

use App\Sheba\Business\BusinessBasicInformation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Sheba\Business\Employee\DashboardMenu;
use Sheba\Dal\ApprovalRequest\Contract as ApprovalRequestRepositoryInterface;
use Sheba\Dal\PayrollSetting\PayrollSetting;
use App\Http\Controllers\Controller;
use App\Models\BusinessMember;
use Illuminate\Http\Request;
use App\Models\Business;
use Sheba\Dal\Visit\Status;
use Sheba\Dal\Visit\VisitRepository;
use Sheba\Dal\LiveTrackingSettings\LiveTrackingSettings;

class DashboardController extends Controller
{
    use BusinessBasicInformation;

    public function index(Request $request, DashboardMenu $menu)
    {
        /** @var BusinessMember $business_member */
        $business_member = $this->getBusinessMember($request);
        if (!$business_member) return api_response($request, null, 404);

        /** @var Business $business */
        $business = $this->getBusiness($request);

        return api_response($request, null, 200, [
            'dashboard' => $menu->get($business, $business_member)
        ]);
    }

    /**
     * @param Request $request
     * @param ApprovalRequestRepositoryInterface $approval_request_repository
     * @param VisitRepository $visit_repository
     * @return JsonResponse
     */
    public function dashboardMenuInfo(Request         $request, ApprovalRequestRepositoryInterface $approval_request_repository,
                                      VisitRepository $visit_repository): JsonResponse
    {
        /** @var BusinessMember $business_member */
        $business_member = $this->getBusinessMember($request);
        if (!$business_member) return api_response($request, null, 404);

        /** @var Business $business */
        $business = $this->getBusiness($request);

        $approval_requests = $approval_request_repository->getApprovalRequestByBusinessMember($business_member);
        $pending_approval_requests_count = $approval_request_repository->countPendingLeaveApprovalRequests($business_member);
        $pending_visit = $visit_repository->where('visitor_id', $business_member->id)
            ->whereIn('status', [Status::CREATED, Status::STARTED, Status::REACHED])->where('schedule_date', '<=', Carbon::now());
        $pending_visit_count = $pending_visit->count();
        $manager = $business ? $business->getActiveBusinessMember()->where('manager_id', $business_member->id)->count() : null;
        $is_manager = $manager ? 1 : 0;

        $data = [
            'is_approval_request_required' => $approval_requests->count() > 0 ? 1 : 0,
            'pending_request' => $pending_approval_requests_count,
            'pending_visit_count' => $pending_visit_count,
            'is_manager' => $is_manager
        ];

        return api_response($request, $business_member, 200, ['info' => $data]);
    }
}
