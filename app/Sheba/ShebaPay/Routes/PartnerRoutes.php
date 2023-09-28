<?php

namespace Sheba\ShebaPay\Routes;


class PartnerRoutes
{
    private $nameSpace = 'App\\Http\\Controllers';
    private $shebaPayNameSpace='Sheba\\ShebaPay\\Controllers';
    public function set($api)
    {
        $api->group(['prefix' => 'v2','middleware'=>[]], function ($api) {
            $api->post('/registration/partner-by-sheba-pay', "{$this->nameSpace}\\Auth\\PartnerRegistrationController@registerShebaPay")
                ->middleware('sheba_pay.basic-auth');
            $api->post('/topup/sheba-pay',"{$this->shebaPayNameSpace}\\TopupController@topup")->middleware(['sheba_pay.basic-auth','topUp.auth']);
            $api->get('/topup/sheba-pay',"{$this->shebaPayNameSpace}\\TopupController@status")->middleware(['topUp.auth']);
        });
        return $api;
    }


}