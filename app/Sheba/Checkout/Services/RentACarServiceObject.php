<?php

namespace Sheba\Checkout\Services;


use App\Models\Thana;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Sheba\Location\Coords;
use Sheba\Location\Distance\Distance;
use Sheba\Location\Distance\DistanceStrategy;

class RentACarServiceObject extends ServiceObject
{

    protected function build()
    {
        $this->setPickUpProperties();
        $this->setDestinationProperties();

        $this->setDropProperties();
        parent::build(); // TODO: Change the autogenerated stub
    }

    private function setPickUpProperties()
    {
        if (isset($this->service->pick_up_geo)) {
            $geo = $this->service->pick_up_geo;
            $this->pickUpLocationLat = (double)$geo->lat;
            $this->pickUpLocationLng = (double)$geo->lng;
        } else {
            $this->pickUpLocationId = (int)$this->service->pick_up_location_id;
            $this->pickUpLocationType = "App\\Models\\" . $this->service->pick_up_location_type;
            $this->pickUpAddress = $this->service->pick_up_address;
            $origin = ($this->pickUpLocationType)::find($this->pickUpLocationId);
            $this->pickUpLocationLat = $origin->lat;
            $this->pickUpLocationLng = $origin->lng;
        }
    }

    private function setDestinationProperties()
    {
        if (isset($this->service->destination_geo)) {
            $geo = $this->service->destination_geo;
            $this->destinationLocationLat = (double)$geo->lat;
            $this->destinationLocationLng = (double)$geo->lng;
        } else {
            $this->destinationLocationId = (int)$this->service->destination_location_id;
            $this->destinationLocationType = "App\\Models\\" . $this->service->destination_location_type;
            $destination = ($this->destinationLocationType)::find($this->destinationLocationId);
            $this->destinationLocationLat = $destination->lat;
            $this->destinationLocationLng = $destination->lng;
        }
        if (isset($this->service->destination_address)) $this->destinationAddress = $this->service->destination_address;
    }

    private function setDropProperties()
    {
        if (isset($this->service->drop_off_date)) $this->dropOffDate = $this->service->drop_off_date;
        if (isset($this->service->drop_off_time)) $this->dropOffTime = $this->service->drop_off_time;
    }

    protected function setQuantity()
    {
        $this->pickUpThana = $this->getThana($this->pickUpLocationLat, $this->pickUpLocationLng, Thana::where('district_id', 1)->get());
        $this->destinationThana = $this->getThana($this->destinationLocationLat, $this->destinationLocationLng, Thana::where('district_id', '<>', 1)->get());
        $data = $this->getDistanceCalculationResult();
        $this->quantity = (double)($data->rows[0]->elements[0]->distance->value) / 1000;
        $this->estimatedTime = (double)($data->rows[0]->elements[0]->duration->value) / 60;
        $this->estimatedDistance = $this->quantity;
    }

    private function getDistanceCalculationResult()
    {
        $client = new Client();
        try {
            $res = $client->request('GET', 'https://maps.googleapis.com/maps/api/distancematrix/json',
                [
                    'query' => ['origins' => "$this->pickUpThana->lat,$this->pickUpThana->lng", 'destinations' => "$this->destinationThana->lat,$this->destinationThana->lng",
                        'key' => env('GOOGLE_DISTANCEMATRIX_KEY'), 'mode' => 'driving']
                ]);
            return json_decode($res->getBody());
        } catch (RequestException $e) {
            return null;
        }
    }

    private function getThana($lat, $lng, $models)
    {
        $current = new Coords($lat, $lng);
        $to = $models->map(function ($model) {
            return new Coords(floatval($model->lat), floatval($model->lng), $model->id);
        })->toArray();
        $distance = (new Distance(DistanceStrategy::$VINCENTY))->matrix();
        $results = $distance->from([$current])->to($to)->sortedDistance()[0];
        $result = array_keys($results)[0];
        return $models->where('id', $result)->first();
    }
}