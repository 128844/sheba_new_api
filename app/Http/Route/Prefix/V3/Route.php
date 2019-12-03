<?php namespace App\Http\Route\Prefix\V3;

class Route
{
    public function set($api)
    {
        $api->group(['prefix' => 'v3', 'namespace' => 'App\Http\Controllers'], function ($api) {
            (new CustomerRoute())->set($api);
            (new AffiliateRoute())->set($api);
            $api->get('locations', 'Location\LocationController@index');
            $api->get('sluggable-type/{slug}', 'ShebaController@getSluggableType');
            $api->get('partners/send-order-requests', 'Partner\PartnerListController@getPartners');
            $api->group(['prefix' => 'rent-a-car'], function ($api) {
                $api->get('prices', 'RentACar\RentACarController@getPrices');
            });
            $api->group(['prefix' => 'register'], function ($api) {
                $api->post('accountkit', 'AccountKit\AccountKitController@continueWithKit');
            });
        });
    }
}
