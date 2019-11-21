<?php namespace App\Http\Route\Prefix\V1\Partner\ID\Auth;

class IndexRoute
{
    public function set($api)
    {
        $api->group(['prefix' => '{partner}', 'middleware' => ['manager.auth']], function ($api) {
            $api->group(['prefix' => 'order-requests'], function ($api) {
                $api->get('/', 'Partner\OrderRequestController@lists');
                /*DUMMY ROUTE FOR CREATE PARTNER ORDER REQUEST*/
                $api->post('create', 'Partner\OrderRequestController@store');
                $api->group(['prefix' => '{partner_order_request}'], function ($api) {
                    $api->post('accept', 'Partner\OrderRequestController@accept');
                    $api->post('decline', 'Partner\OrderRequestController@decline');
                });
            });
        });
    }
}
