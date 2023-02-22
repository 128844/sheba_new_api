<?php

namespace App\Http\Route\Prefix\V1\Partner;

use App\Http\Route\Prefix\V1\Partner\ID\Auth\AamarpayRoute;
use App\Http\Route\Prefix\V1\Partner\ID\Auth\DynamicFormRoute;
use App\Http\Route\Prefix\V1\Partner\ID\Auth\IndexRoute as IDAuthRoute;
use App\Http\Route\Prefix\V1\Partner\ID\Auth\ExternalPaymentLinkRoute;
use App\Http\Route\Prefix\V1\Partner\ID\Auth\MtbRoute;
use App\Http\Route\Prefix\V1\Partner\ID\Auth\QRPaymentRoute;
use App\Http\Route\Prefix\V1\Partner\ID\Auth\ResellerPaymentRoute;
use App\Http\Route\Prefix\V1\Partner\ID\NonAuth\IndexRoute as IDNonAuthRoute;

class PartnerRoute
{
    public function set($api)
    {
        $api->group(['prefix' => 'partners'], function ($api) {
            (new IDNonAuthRoute())->set($api);
            (new IDAuthRoute())->set($api);
            $api->get('search', 'Partner\PartnerPosController@search');
        });
        (new DynamicFormRoute())->set($api);
        (new ExternalPaymentLinkRoute())->set($api);
        (new ResellerPaymentRoute())->set($api);
        (new QRPaymentRoute())->set($api);
        (new MtbRoute())->set($api);
        (new AamarpayRoute())->set($api);
    }
}
