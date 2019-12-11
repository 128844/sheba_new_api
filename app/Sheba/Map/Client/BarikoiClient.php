<?php namespace Sheba\Map\Client;

use GuzzleHttp\Exception\RequestException;
use Sheba\Location\Geo;
use GuzzleHttp\Client as HTTPClient;
use Sheba\Map\Address;

class BarikoiClient implements Client
{
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = config('barikoi.api_key');
    }

    public function getAddressFromGeo(Geo $geo): Address
    {
        try {
            $client = new HTTPClient();
            $response = $client->request('GET', 'https://barikoi.xyz/v1/api/search/reverse/' . $this->apiKey . '/geocode', [
                'query' => [
                    'latitude' => $geo->getLat(),
                    'longitude' => $geo->getLng(),
                ]
            ]);
            $response = json_decode($response->getBody());
            $address = new Address();
            if (!isset($response->place)) return $address->setAddress(null);
            $place = $response->place;
            return $address->setAddress($place->address . ', ' . $place->area . ', ' . $place->city);
        } catch (RequestException $e) {
            throw $e;
        }
    }

    public function getGeoFromAddress(Address $address): Geo
    {
        try {
            $client = new HTTPClient();
            $response = $client->request('POST', 'https://barikoi.xyz/v1/api/search/' . $this->apiKey . '/rupantor/geocode', [
                'form_params' => [
                    'q' => $address->getAddress()
                ]
            ]);
            $response = json_decode($response->getBody());
            if (!isset($response->geocoded_address->latitude)) return null;
            $geo = new Geo();
            return $geo->setLat($response->geocoded_address->latitude)->setLng($response->geocoded_address->longitude);
        } catch (RequestException $e) {
            throw $e;
        }
    }
}