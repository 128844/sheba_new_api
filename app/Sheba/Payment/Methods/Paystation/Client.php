<?php

namespace Sheba\Payment\Methods\Paystation;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;

class Client
{
    /** @var HttpClient */
    private $httpClient;

    private $baseUrl;

    public function __construct(HttpClient $client)
    {
        $this->httpClient = $client;

        $this->baseUrl = config('payment.paystation.base_url');
    }

    /**
     * @param $uri
     * @param $data
     * @return object
     */
    public function post($uri, $data, $headers = [])
    {
        return $this->call('post', $uri, $this->getOptionsForPost($data, $headers));
    }

    /**
     * @param $method
     * @param $uri
     * @param $options
     * @return object
     */
    private function call($method, $uri, $options)
    {
        try {
            $res = $this->httpClient->request(strtoupper($method), $this->makeUrl($uri), $options);
            return decodeGuzzleResponse($res, false);
        } catch (GuzzleException $e) {
            throw new PaystationNotWorking($e->getMessage());
        }
    }

    /**
     * @param $uri
     * @return string
     */
    private function makeUrl($uri)
    {
        return rtrim($this->baseUrl, '/') . "/" . ltrim($uri, '/');
    }

    private function getOptionsForPost($data, $headers = [])
    {
        return $this->getOptions($data, $headers);
    }

    private function getOptions($data = null, $headers = [])
    {
        $options['headers'] = $this->getHeaders($headers);
        if ($data) {
            $options['json'] = $data;
        }
        return $options;
    }

    private function getHeaders($headers = [])
    {
        return $headers + [
            "Content-Type" => "application/json"
        ];
    }
}
