<?php

namespace Sheba\ShebaPay\Routes;


class PartnerRoutes
{
    private $nameSpace = 'App\\Http\\Controllers';

    public function set($api)
    {
        $api->version('v1', function ($api) {
            $this->loadRoutes($api);
        });
    }

    public function loadRoutes($api)
    {
        $api->group(['prefix' => 'v2'], function ($api) {
            $api->post('/registration/partner-by-sheba-pay', "{$this->nameSpace}\\Auth\\PartnerRegistrationController@registerShebaPay");
        });
    }

}