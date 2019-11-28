<?php namespace App\Http\Controllers\RentACar;


use App\Http\Controllers\Controller;
use App\Models\HyperLocal;
use App\Models\LocationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sheba\LocationService\DiscountCalculation;
use Sheba\LocationService\PriceCalculation;
use Sheba\ServiceRequest\ServiceRequest;
use Sheba\ServiceRequest\ServiceRequestObject;

class RentACarController extends Controller
{
    public function getPrices(Request $request, ServiceRequest $service_request, PriceCalculation $price_calculation, DiscountCalculation $discount_calculation)
    {
        try {
            $this->validate($request, ['services' => 'required|string', 'lat' => 'required|numeric', 'lng' => 'required|numeric']);
            $hyper_local = HyperLocal::insidePolygon($request->lat, $request->lng)->with('location')->first();
            /** @var ServiceRequestObject[] $services */
            $services = $service_request_object = $service_request->setServices(json_decode($request->services, 1))->get();
            $service = $services[0];
            $location_service = LocationService::where([['location_id', $hyper_local->location->id], ['service_id', $service->getServiceId()]])->first();
            $price_calculation->setLocationService($location_service)->setOption($service->getOption())->setQuantity($service->getQuantity());
            $original_price = $price_calculation->getTotalOriginalPrice();
            $discount_calculation->setLocationService($location_service)->setOriginalPrice($original_price)->calculate();
            return api_response($request, null, 200, ['price' => [
                'discounted_price' => $discount_calculation->getDiscountedPrice(),
                'original_price' => $original_price,
                'discount' => $discount_calculation->getDiscount(),
                'quantity' => $service->getQuantity()
            ]]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
}