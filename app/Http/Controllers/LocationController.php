<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\HyperLocal;
use App\Models\Location;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Contracts\Validation\ValidationException;
use Illuminate\Http\Request;
use Sheba\Location\Coords;
use Sheba\Location\Distance\Distance;
use Sheba\Location\Distance\DistanceStrategy;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $cities = City::whereHas('locations', function ($q) {
                $q->published();
            })->with(['locations' => function ($q) {
                $q->select('id', 'city_id', 'name', 'geo_informations');
            }])->select('id', 'name')->get();
            foreach ($cities as $city) {
                foreach ($city->locations as &$location) {
                    if ($location->geo_informations) {
                        $geo = json_decode($location->geo_informations);
                        $location->center = isset($geo->center) ? $geo->center : null;
                        array_forget($location, 'geo_informations');
                    }
                }
            }
            if (count($cities) > 0) {
                return api_response($request, $cities, 200, ['cities' => $cities]);
            } else {
                return api_response($request, null, 404);
            }
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getAllLocations(Request $request)
    {
        try {
            if (($request->hasHeader('Portal-Name') && $request->header('Portal-Name') == 'manager-app') || ($request->has('for') && $request->for == 'partner')) {
                $locations = Location::select('id', 'name')->where('is_published_for_partner', 1)->orderBy('name')->get();
                return response()->json(['locations' => $locations, 'code' => 200, 'msg' => 'successful']);
            }
            $locations = Location::select('id', 'name')->where([
                ['name', 'NOT LIKE', '%Rest%'],
                ['publication_status', 1]
            ])->orderBy('name')->get();

            Location::select('id', 'name')->where([
                ['name', 'LIKE', '%Rest%'],
                ['publication_status', 1]
            ])->get()->each(function ($location, $key) use ($locations) {
                $locations->push($location);
            });
            return response()->json(['locations' => $locations, 'code' => 200, 'msg' => 'successful']);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }

    }

    public function getCurrent(Request $request)
    {
        try {
            $this->validate($request, [
                'lat' => 'required|numeric',
                'lng' => 'required|numeric',
            ]);
            $hyper_local = HyperLocal::insidePolygon($request->lat, $request->lng)->with('location')->first();
            if ($hyper_local) {
                return api_response($request, $hyper_local->location, 200, ['location' => collect($hyper_local->location)->only(['id', 'name'])]);
            } else {
                return api_response($request, null, 404);
            }
//            $current = new Coords($request->lat, $request->lng);
//            $to = $hyper_locals->filter(function ($hyper_local) {
//                return $hyper_local->location->publication_status == 1;
//            })->map(function ($hyper_local) {
//                $geo = json_decode($hyper_local->location->geo_informations);
//                return new Coords(floatval($geo->lat), floatval($geo->lng), $hyper_local->location->id);
//            })->toArray();
//            dd($to);
//            $distance = (new Distance(DistanceStrategy::$VINCENTY))->matrix();
//            $results = $distance->from([$current])->to($to)->sortedDistance()[0];
//            $final = collect();
//            foreach ($results as $key => $result) {
//                dd($result);
//                $location = $locations->where('id', $key)->first();
//                if ($result <= (double)json_decode($location->geo_informations)->radius * 1000) {
//                    $final->push($location);
//                    break;
//                }
//            }
//            if ($final->count() == 0) {
//                $final->push($locations->where('id', array_keys($results)[0])->first());
//            }
//            return api_response($request, $final->first(), 200, ['location' => collect($final->first())->only(['id', 'name'])]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    private function getOriginsForDistanceMatrix($locations)
    {
        $origins = '';
        foreach ($locations as $location) {
            $geo_info = json_decode($location->geo_informations);
            $origins .= "$geo_info->lat,$geo_info->lng|";
        }
        return rtrim($origins, "|");
    }

    private function getDistanceCalculationResult($lat, $lng, $origins)
    {
        $client = new Client();
        try {
            $res = $client->request('GET', 'https://maps.googleapis.com/maps/api/distancematrix/json',
                [
                    'query' => ['origins' => $origins, 'destinations' => "$lat,$lng", 'key' => env('GOOGLE_DISTANCEMATRIX_KEY')]
                ]);
            return json_decode($res->getBody());
        } catch (RequestException $e) {
            return null;
        }
    }

    private function calculateDistance(Request $request, $geo_info)
    {
        $lat1 = $geo_info->lat;
        $lng1 = $geo_info->lng;
        $lat2 = $request->lat;
        $lng2 = $request->lng;

        return ROUND((6371.0 * ACOS(SIN($lat1 * PI() / 180) * SIN($lat2 * PI() / 180) + COS($lat1 * PI() / 180) * COS($lat2 * PI() / 180) * COS(($lng1 * PI() / 180) - ($lng2 * PI() / 180)))), 2);
    }

}
