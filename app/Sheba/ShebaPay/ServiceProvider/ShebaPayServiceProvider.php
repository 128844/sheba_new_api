<?php namespace Sheba\ShebaPay\ServiceProvider;

use Illuminate\Support\ServiceProvider;
use Sheba\ShebaPay\Middlewares\ShebaPayBasicAuthMiddleware;
use Sheba\ShebaPay\Routes\PartnerRoutes;

class ShebaPayServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $router = $this->app['router'];
        $router->middleware('sheba_pay.basic-auth', ShebaPayBasicAuthMiddleware::class);
//        (new ShebaPayPartnerRoutes())->set($router);
//        $api = app('Dingo\Api\Routing\Router');
//        (new PartnerRoutes())->set($api);
    }

    public function register()
    {
        //
    }
}