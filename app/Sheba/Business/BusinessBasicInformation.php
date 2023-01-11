<?php

namespace App\Sheba\Business;

use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Member;
use App\Models\Profile;
use Illuminate\Http\Request;

trait BusinessBasicInformation
{
    public function getBusinessMember(Request $request)
    {
        $auth_info = $request->auth_info;
        $business_member = $auth_info['business_member'];
        if (!isset($business_member['id'])) return null;
        return BusinessMember::findOrFail($business_member['id']);
    }

    public function getBusinessMemberById($business_member)
    {
        return BusinessMember::findOrFail($business_member);
    }

    public function getMember(Request $request)
    {
        $auth_info = $request->auth_info;
        $business_member = $auth_info['business_member'];
        if (!isset($business_member['member_id'])) return null;
        return Member::findOrFail($business_member['member_id']);
    }

    public function getBusiness(Request $request)
    {
        $auth_info = $request->auth_info;
        $business_member = $auth_info['business_member'];
        if (!isset($business_member['business_id'])) return null;
        return Business::findOrFail($business_member['business_id']);
    }

    public function getProfile(Request $request)
    {
        $auth_info = $request->auth_info;
        $profile = $auth_info['profile'];
        if (!isset($profile['id'])) return null;
        return Profile::findOrFail($profile['id']);
    }

    public function isDefaultImageByUrl($logo_url)
    {
        if (empty($logo_url) || $logo_url === '' || $logo_url === null) return 1;
        $path_info = pathinfo($logo_url);
        if (!in_array($path_info['extension'], ['png', 'jpg', 'jpeg', 'svg', 'gif']) || strtolower($path_info['filename']) == 'default') return 1;
        return 0;
    }
}
