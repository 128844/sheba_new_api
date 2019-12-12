<?php

namespace App\Http\Controllers;

use App\Models\Navigation;
use App\Repositories\ServiceRepository;
use Illuminate\Http\Request;

class NavigationController extends Controller
{
    private $serviceRepository;

    public function __construct()
    {
        $this->serviceRepository = new ServiceRepository();
    }

    public function getNavList(Request $request)
    {
        $navs = Navigation::with(['groups' => function ($q) {
            $q->select('_id', 'name', 'navigation_id', 'services')->with(['navServices' => function ($q) {
                $q->select('*')->where('publication_status', 1);
            }]);
        }])->select('_id', 'name')->where('publication_status', 1)->get();
        foreach ($navs as $nav) {
            foreach ($nav->groups as $group) {
                foreach ($group->navServices as $service) {
                    array_forget($service, ['updated_at', 'created_at', 'group_ids']);
                }
            }
        }
        if (count($navs) != 0) {
            return api_response($request, $navs, 200, ['navigations' => $navs]);
        } else {
            return api_response($request, $navs, 404);
        }
    }

    public function getServices($navigation, Request $request)
    {
        $navigation = Navigation::where('name', 'like', '%' . $navigation . '%')->first();
        if ($navigation != null) {
            $services = $this->serviceRepository->addServiceInfo($navigation->services(), $request->location);
            if (count($services) > 0) {
                return response()->json(['services' => $services, 'code' => 200]);
            }
        }
        return response()->json(['msg' => 'not found', 'code' => 404]);
    }

    public function getRedirectUrl(Request $request)
    {
        dd(123);
    }
}
