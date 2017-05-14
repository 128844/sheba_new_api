<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Repositories\BusinessMemberRepository;
use App\Repositories\BusinessRepository;
use Illuminate\Http\Request;

class BusinessMemberController extends Controller
{
    private $businessRepository;
    private $businessMemberRepository;

    public function __construct()
    {
        $this->businessRepository = new BusinessRepository();
        $this->businessMemberRepository = new BusinessMemberRepository();
    }

    public function getMember($member, $business, Request $request)
    {
        $member = Member::find($member);
        $business = $this->businessRepository->businessExistsForMember($member, $business);
        if ($business != null) {
            $member = $this->businessMemberRepository->isBusinessMember($business, $request->business_member);
            if ($member != null) {
                return response()->json(['member' => $this->businessMemberRepository->getInfo($member), 'code' => 200]);
            } else {
                return response()->json(['code' => 409, 'msg' => 'conflict']);
            }
        } else {
            return response()->json(['code' => 409, 'msg' => 'conflict']);
        }
    }
}
