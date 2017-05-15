<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Repositories\InvitationRepository;
use App\Repositories\MemberRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemberController extends Controller
{
    private $invitationRepository;
    private $memberRepository;

    public function __construct()
    {
        $this->invitationRepository = new InvitationRepository();
        $this->memberRepository = new MemberRepository();
    }

    public function getRequests($member)
    {
        $member = Member::with(['profile' => function ($q) {
            $q->select('id')->with(['joinRequests' => function ($q) {
                $q->select('id', 'profile_id', 'organization_type', 'organization_id')->where('status', 'Pending');
            }]);
        }])->select('id', 'profile_id')->where('id', $member)->first();
        foreach ($member->profile->joinRequests as $request) {
            array_add($request, 'business', $request->organization->select('id', 'name', 'sub_domain', 'logo', 'address')->first());
            array_forget($request, 'organization');
            array_forget($request, 'organization_type');
            array_forget($request, 'organization_id');
        }
        if (count($member->profile->joinRequests) > 0) {
            return response()->json(['code' => 200, 'requests' => $member->profile->joinRequests]);
        } else {
            return response()->json(['code' => 404]);
        }
    }

    public function manageInvitation($member, Request $request)
    {
        $join_request = Member::find($member)->profile->joinRequests()->where('id', $request->invitation)->first();
        if (count($join_request) != 0) {
            return $this->invitationRepository->manage($join_request, $request->status) ? response()->json(['code' => 200]) : response()->json(['code' => 500]);
        } else {
            return response()->json(['code' => 409]);
        }
    }

    public function getInfo($member)
    {
        $member = Member::find($member);
        return response()->json(['member' => $this->memberRepository->getInfo($member), 'code' => 200]);
    }

    public function update($member, Request $request)
    {
        $member = Member::find($member);
        $member = $this->memberRepository->updateInfo($member, $request);
        return $member ? response()->json(['member' => $member, 'code' => 200]) : response()->json(['code' => 404]);
    }

    public function changeNID($member, Request $request)
    {
        $member = Member::find($member);
        if ($member->nid_image != '') {
            $this->memberRepository->deleteFileFromCDN($member->nid_image);
        }
        $member->nid_image = $this->memberRepository->uploadImage($member, $request->file('nid_image'));
        return $member->update() ? response()->json(['code' => 200]) : response()->json(['code' => 404]);
    }
}
