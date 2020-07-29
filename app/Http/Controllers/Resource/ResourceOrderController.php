<?php namespace App\Http\Controllers\Resource;

use App\Models\Job;
use App\Models\Location;
use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Sheba\Authentication\AuthUser;
use Sheba\Jobs\AcceptJobAndAssignResource;
use Sheba\Location\Geo;
use Sheba\Order\OrderCreateRequest;

class ResourceOrderController extends Controller
{
    public function placeOrder(Request $request, Geo $geo, OrderCreateRequest $orderCreateRequest)
    {
        $request->merge(['mobile' => formatMobile($request->mobile)]);
        $this->validate($request, [
            'mobile' => 'required|string|mobile:bd',
            'name' => 'required|string',
            'services' => 'required|string',
            'sales_channel' => 'required|string',
            'date' => 'required|date_format:Y-m-d|after:' . Carbon::yesterday()->format('Y-m-d'),
            'time' => 'required|string',
            'payment_method' => 'required|string|in:cod,online,wallet,bkash,cbl,partner_wallet',
            'location_id' => 'required|numeric',
            'address' => 'required|string',
            'partner' => 'required|numeric',
        ], ['mobile' => 'Invalid mobile number!']);

        /** @var AuthUser $auth_user */
        $auth_user = $request->auth_user;
        $resource = $auth_user->getResource();
        $location = Location::find($request->location_id);
        $geo_info = json_decode($location->geo_informations);
        $geo->setLat($geo_info->lat)->setLng($geo_info->lng);
        $response = $orderCreateRequest->setGeo($geo)->setServices($request->services)->setDate($request->date)->setTime($request->time)
            ->setPartnerId($request->partner)->setMobile($request->mobile)->setName($request->name)->setAddress($request->address)
            ->setAdditionalInformation($request->additional_information)->setSalesChannel($request->sales_channel)
            ->setPaymentMethod($request->payment_method)->setAssignResource($request->assign_resource)->setRequest($request)->setResource($resource)->create();
        return api_response($request, null, $response->getCode(), ['message' => $response->getMessage()]);
    }
}
