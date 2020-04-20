<?php namespace App\Http\Controllers\Service;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Sheba\Services\ServicePriceCalculation;

class ServicePricingController extends Controller
{
    public function getCalculatedPrice(Request $request, ServicePriceCalculation $servicePriceCalculation)
    {
        $this->validate($request, [
            'services' => 'required|string', 'lat' => 'required|numeric', 'lng' => 'required|numeric',
        ]);
        if ($request->services == '[]')  return api_response($request, null, 403, ['message' => 'You need to add a service']);
        $price = $servicePriceCalculation->setLocation($request->lat, $request->lng)->setServices($request->services)->getCalculatedPrice();
        return api_response($request, $price, 200, ['service_pricing' => $price]);
    }
}
