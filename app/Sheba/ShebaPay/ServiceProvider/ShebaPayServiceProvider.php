<?php namespace Sheba\ShebaPay\ServiceProvider;

use Illuminate\Support\ServiceProvider;
use Sheba\ShebaPay\Middlewares\ShebaPayBasicAuthMiddleware;

class ShebaPayServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $router = $this->app['router'];
        $router->middleware('sheba_pay.basic-auth', ShebaPayBasicAuthMiddleware::class);
    }

    public function register()
    {
        //
    }
}