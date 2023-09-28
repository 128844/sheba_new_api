<?php

namespace Sheba\ShebaPay\Responses;

use App\Models\Partner;
use App\Models\Profile;

class PartnerRegistrationResponse
{
    public static function get(Profile $profile, array $info, string $token)
    {
        $profile = $profile->toArray();
        $data = $info['partner'];
        unset($info['partner']);
        $info['profile'] = ['id' => $profile['id'], 'avatar' => $profile['pro_pic'], 'mobile' => $profile['mobile']];
        $data['resource'] = $info;
        $data['token'] = $token;
        return $data;
    }
}