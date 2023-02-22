<?php

namespace App\Http\Route\Prefix\V1\Partner\ID\Auth;

class AamarpayRoute
{
    public function set($api)
    {
        $api->group(['middleware' => ['accessToken']], function ($api) {
            $api->post('partners/aamarpay-apply', 'Aamarpay\AamarpayController@apply');
        });
    }
}
