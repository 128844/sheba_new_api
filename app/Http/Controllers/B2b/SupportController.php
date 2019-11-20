<?php namespace App\Http\Controllers\B2b;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Sheba\Business\Support\Updater;
use Sheba\Dal\Support\SupportRepositoryInterface;
use Sheba\Repositories\Interfaces\BusinessMemberRepositoryInterface;

class SupportController extends Controller
{

    public function resolve($member, $support, SupportRepositoryInterface $support_repository, BusinessMemberRepositoryInterface $business_member_repository,
                            Updater $updater, Request $request)
    {
        try {
            $support = $support_repository->where('id', $support)->first();
            if (!$support) return api_response($request, null, 404);
            $business_member = $request->business_member;
            $support = $updater->setSupport($support)->setBusinessMember($business_member)->resolve();
            if (!$support) return api_response($request, null, 500);
            return api_response($request, $support, 200);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function index($business, Request $request, SupportRepositoryInterface $support_repository, BusinessMemberRepositoryInterface $business_member_repository)
    {
        try {
            $members = $business_member_repository->where('business_id', $business)->select('id', 'member_id')->get()->pluck('member_id')->toArray();
            list($offset, $limit) = calculatePagination($request);
            $supports = $support_repository->whereIn('member_id', $members)
                ->select('id', 'member_id', 'status', 'long_description', 'created_at', 'closed_at', 'is_satisfied');
            if ($request->has('status')) $supports = $supports->where('status', $request->status);
            if ($request->has('limit')) $supports = $supports->skip($offset)->limit($limit);
            $supports = $supports->orderBy('id', 'desc')->get();
            if (count($supports) == 0) return api_response($request, null, 404);
            $supports->map(function (&$support) {
                $support['date'] = $support->created_at->format('M d');
                $support['time'] = $support->created_at->format('h:i A');
                return $support;
            });
            return api_response($request, $supports, 200, ['supports' => $supports, 'total_supports' => $support_repository->get()->count()]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function show(Request $request, $support, SupportRepositoryInterface $support_repository)
    {
        try {
            $business_member = $request->business_member;
            $support = $support_repository->where('id', $support)->select('id', 'member_id', 'status', 'long_description', 'created_at', 'is_satisfied', 'closed_at')->first();
            if (!$support) return api_response($request, null, 404);
            $support['date'] = $support->created_at->format('M d');
            $support['time'] = $support->created_at->format('h:i A');
            $support['requested_by'] = [
                'name' => $support->member->profile->name,
                'image' => $support->member->profile->pro_pic,
                'designation' => $support->member->businessMember->role ? $support->member->businessMember->role->name : ''
            ];
            removeRelationsAndFields($support);
            return api_response($request, $support, 200, ['support' => $support]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
}