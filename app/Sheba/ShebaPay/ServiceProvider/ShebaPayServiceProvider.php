<?php

namespace Sheba\ShebaPay\ServiceProvider;

use Illuminate\Routing\Router;
use Sheba\ShebaPay\Routes\PartnerRoutes as ShebaPayPartnerRoutes;
use Illuminate\Support\ServiceProvider;
use Sheba\ShebaPay\Middlewares\ShebaPayBasicAuthMiddleware;

class ShebaPayServiceProvider extends ServiceProvider
{
    public function boot(Router $router)
    {
//        $router->middleware('sheba_pay.basic-auth', ShebaPayBasicAuthMiddleware::class);
//        (new ShebaPayPartnerRoutes())->set($router);
//        $api = app('Dingo\Api\Routing\Router');
//        (new ShebaPayPartnerRoutes())->set($api);
    }

    public function register()
    {
        //
    }
}