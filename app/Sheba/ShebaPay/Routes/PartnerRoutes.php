<?php

namespace Sheba\ShebaPay\Routes;


class PartnerRoutes
{
    private $nameSpace = 'App\\Http\\Controllers';

    public function set($api)
    {
        $api->group(['prefix' => 'v2'], function ($api) {
            $api->post('/registration/partner-by-sheba-pay', "{$this->nameSpace}\\Auth\\PartnerRegistrationController@registerShebaPay")
                ->middleware('sheba_pay.basic-auth');
            $api->post('/topup/sheba-pay',"Sheba\\ShebaPay\\Controllers\\TopupController@topup")->middleware('topUp.auth');
        });
        return $api;
    }

    public function loadRoutes($api)
    {

    }

}