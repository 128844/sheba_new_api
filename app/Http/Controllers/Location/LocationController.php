<?php namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{

    public function index(Request $request)
    {
        try {
            $dhaka = Location::published()->where('city_id', 1)->select('id', 'city_id', 'name', 'geo_informations')->get();
            $chittagong = Location::published()->where('city_id', 2)->select('id', 'city_id', 'name', 'geo_informations')->get();
            $cities = [
                [
                    'id' => 1,
                    'name' => 'Dhaka',
                    'image' => "https://cdn-shebadev.s3.ap-south-1.amazonaws.com/sheba_xyz/jpg/dhaka.jpg",
                    'center' => [
                        'lat' => 23.788994076131,
                        'lng' => 90.410852011945
                    ],
                    'locations' => $dhaka->reject(function ($location) {
                        $geo = json_decode($location->geo_informations);
                        return !isset($geo->center);
                    })->map(function ($location) {
                        $location['id'] = $location->id;
                        $location['name'] = $location->name;
                        $location['center'] = json_decode($location->geo_informations)->center;
                        array_forget($location, 'geo_informations');
                        return $location;
                    })->values()->all()
                ],
                [
                    'id' => 2,
                    'name' => 'Chittagong',
                    'image' => "https://cdn-shebadev.s3.ap-south-1.amazonaws.com/sheba_xyz/jpg/chittagong.jpg",
                    'center' => [
                        'lat' => 22.35585575222634,
                        'lng' => 91.85625492089844
                    ],
                    'locations' => $chittagong->reject(function ($location) {
                        $geo = json_decode($location->geo_informations);
                        return !isset($geo->center);
                    })->map(function ($location) {
                        $location['id'] = $location->id;
                        $location['name'] = $location->name;
                        $location['center'] = json_decode($location->geo_informations)->center;
                        array_forget($location, 'geo_informations');
                        return $location;
                    })->values()->all()
                ]
            ];
            return api_response($request, $cities, 200, ['cities' => $cities]);
        } catch (\Throwable $e) {
            return api_response($request, null, 500);
        }
    }

}