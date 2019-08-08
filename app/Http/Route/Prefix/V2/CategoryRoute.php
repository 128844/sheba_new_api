<?php namespace App\Http\Route\Prefix\V2;


class CategoryRoute
{
    public function set($api)
    {
        $api->group(['prefix' => 'categories'], function ($api) {
            $api->get('/', 'CategoryController@getAllCategories');
        });
    }
}