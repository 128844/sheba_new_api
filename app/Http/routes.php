<?php


use App\Models\PartnerOrder;
use Sheba\AutoSpAssign\Initiator;

Route::get('/', function () {
    /** @var Initiator $init */
    $init = app(Initiator::class);
    $init->setPartnerIds([3836, 37662,37914])->setPartnerOrder(PartnerOrder::where('order_id', 161022)->first())->initiate();
    return ['code' => 200, 'message' => "Success. This project will hold the api's"];
});

$api = app('Dingo\Api\Routing\Router');

/*
|--------------------------------------------------------------------------
| Version Reminder
|--------------------------------------------------------------------------
|
| When next version comes add a prefix to the old version
| routes and change API_PREFIX in api.php file to null
|
|
*/

$api->version('v1', function ($api) {
    (new App\Http\Route\Prefix\V1\Route())->set($api);
    (new App\Http\Route\Prefix\V2\Route())->set($api);
    (new App\Http\Route\Prefix\V3\Route())->set($api);
});
