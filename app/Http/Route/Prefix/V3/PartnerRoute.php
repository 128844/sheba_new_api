<?php namespace App\Http\Route\Prefix\V3;

class PartnerRoute
{
    public function set($api)
    {
        $api->group(['prefix' => 'partners/{partner}', 'middleware' => ['manager.auth']], function ($api) {
            $api->get('dashboard','Partner\DashboardController@getV3');
            $api->get('home-setting', 'Partner\DashboardController@getHomeSettingV3');
            $api->post('home-setting', 'Partner\DashboardController@updateHomeSetting');
            $api->get('feature-videos', 'Partner\DashboardController@getFeatureVideos');
        });
    }
}
