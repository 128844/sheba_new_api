<?php namespace Sheba\ServiceRequest;


use App\Exceptions\HyperLocationNotFoundException;
use App\Exceptions\RentACar\DestinationCitySameAsPickupException;
use App\Exceptions\RentACar\InsideCityPickUpAddressNotFoundException;
use App\Exceptions\RentACar\OutsideCityPickUpAddressNotFoundException;
use App\Exceptions\ServiceRequest\MultipleCategoryServiceRequestException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Validation\ValidationException;
use Sheba\Location\Geo;
use Sheba\Map\MapClientNoResultException;
use Sheba\ServiceRequest\Exception\ServiceIsUnpublishedException;

class ServiceRequest
{
    private $services;
    private $validator;

    public function __construct(Validator $validator)
    {
        $this->validator = $validator;
    }

    public function setServices(array $services)
    {
        $this->services = $services;
        return $this;
    }


    /**
     * @return  ServiceRequestObject[]
     * @throws DestinationCitySameAsPickupException
     * @throws InsideCityPickUpAddressNotFoundException
     * @throws MultipleCategoryServiceRequestException
     * @throws OutsideCityPickUpAddressNotFoundException
     * @throws ServiceIsUnpublishedException
     * @throws ValidationException
     * @throws HyperLocationNotFoundException
     * @throws GuzzleException
     * @throws MapClientNoResultException
     */
    public function get()
    {
        $this->validate();
        $final = [];
        foreach ($this->services as $service) {
            /** @var ServiceRequestObject $serviceRequestObject */
            $serviceRequestObject = app(ServiceRequestObject::class);
            $serviceRequestObject->setServiceId($service['id'])->setQuantity($service['quantity']);
            if (isset($service['pick_up_location_geo']) && isset($service['pick_up_location_geo']['lat']) && isset($service['pick_up_location_geo']['lng'])) {
                $geo = new Geo();
                $serviceRequestObject->setPickUpGeo($geo->setLat($service['pick_up_location_geo']['lat'])->setLng($service['pick_up_location_geo']['lng']));
            }
            if (isset($service['destination_location_geo']) && isset($service['destination_location_geo']['lat']) && isset($service['destination_location_geo']['lng'])) {
                $geo = new Geo();
                $serviceRequestObject->setDestinationGeo($geo->setLat($service['destination_location_geo']['lat'])
                    ->setLng($service['destination_location_geo']['lng']));
            }
            if (isset($service['pick_up_address'])) $serviceRequestObject->setPickUpAddress($service['pick_up_address']);
            if (isset($service['destination_address'])) $serviceRequestObject->setDestinationAddress($service['destination_address']);
            if (isset($service['drop_off_date'])) $serviceRequestObject->setDropOffDate($service['drop_off_date']);
            if (isset($service['drop_off_time'])) $serviceRequestObject->setDropOffTime($service['drop_off_time']);
            if (isset($service['option'])) $serviceRequestObject->setOption(array_map('intval', $service['option']));
            $serviceRequestObject->build();
            array_push($final, $serviceRequestObject);
        }
        $this->checkForMultipleCategory($final);
        return $final;
    }

    /**
     * @throws ValidationException
     */
    private function validate()
    {
        $this->validator->setServices($this->services)->validate();
        if ($this->validator->hasError()) throw new ValidationException($this->validator->getErrors());
    }


    /**
     * @param ServiceRequestObject[] $services
     * @throws MultipleCategoryServiceRequestException
     */
    private function checkForMultipleCategory($services)
    {
        $category_ids = [];
        foreach ($services as $service) {
            array_push($category_ids, $service->getCategory()->id);
        }
        if (count(array_unique($category_ids)) > 1) throw new MultipleCategoryServiceRequestException();
    }

}