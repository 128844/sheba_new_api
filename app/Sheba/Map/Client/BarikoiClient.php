<?php namespace Sheba\Map\Client;

use App\Sheba\Map\BarikoiAddress;
use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Sheba\Map\Distance;
use Sheba\Map\MapClientNoResultException;
use Sheba\Location\Geo;
use GuzzleHttp\Client as HTTPClient;
use Sheba\Map\Address;

class BarikoiClient implements Client
{
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('barikoi.api_key');
        $this->baseUrl = "https://barikoi.xyz/v1/api/search";
    }

    public function getAddressFromGeo(Geo $geo): Address
    {
        $address = new BarikoiAddress();
        if ($geo->isNull()) return $address->setAddress(null);

        try {
            $client = new HTTPClient();
            $url = "$this->baseUrl/reverse/geocode/server/$this->apiKey/place";
            $params = [
                'latitude' => $geo->getLat(),
                'longitude' => $geo->getLng(),
                'address' => 'true',
                'area' => 'true'
            ];
            $response = $client->request('GET', $url, [
                'query' => $params
            ]);
            $response = json_decode($response->getBody());

            $this->log($url, $params, $response);

            if (!isset($response->place)) return $address->setAddress(null);
            $place = $response->place;
            return $address->handleFullAddress($place);
        } catch (RequestException $e) {
            throw $e;
        }
    }

    /**
     * @param Address $address
     * @return Geo
     * @throws GuzzleException
     * @throws MapClientNoResultException
     */
    public function getGeoFromAddress(Address $address): Geo
    {
        $client = new HTTPClient();
        $url = "$this->baseUrl/$this->apiKey/rupantor/geocode";
        $params = [
            'q' => $address->getAddress()
        ];

        $response = $client->request('POST', $url, [
            'form_params' => $params
        ]);
        $response = json_decode($response->getBody());

        $this->log($url, $params, $response);

        if (!isset($response->geocoded_address->latitude)) throw new MapClientNoResultException('Invalid Address');
        $geo = new Geo();
        return $geo->setLat($response->geocoded_address->latitude)->setLng($response->geocoded_address->longitude);
    }

    public function getDistanceBetweenTwoPoints(Geo $from, Geo $to) :Distance
    {
        // TODO: Implement getDistanceBetweenTwoPoints() method.
    }

    private function log($barikoi_url, $barikoi_body, $response)
    {
        try {
            DB::table('barikoi_logs')->insert([
                "url" => request()->url(),
                "headers" => json_encode(request()->header()),
                "body" => json_encode(request()->all()),
                "request_url" => $barikoi_url,
                "request_body" => json_encode($barikoi_body),
                "response" => json_encode($response),
                "created_at" => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            logError($e);
        }
    }
}
