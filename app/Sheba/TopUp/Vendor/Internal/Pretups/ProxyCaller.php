<?php namespace Sheba\TopUp\Vendor\Internal\Pretups;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;

class ProxyCaller extends Caller
{
    /** @var HttpClient */
    private $httpClient;
    private $proxyUrl;

    public function __construct(HttpClient $client)
    {
        $this->httpClient = $client;
    }

    public function setProxyUrl($proxy_url)
    {
        $this->proxyUrl = $proxy_url;
        return $this;
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    public function call()
    {
        $result = $this->httpClient->request('POST', $this->proxyUrl, [
            'form_params' => [
                'url' => $this->url,
                'input' => $this->input
            ]
        ]);

        $response = json_decode($result->getBody()->getContents())->endpoint_response;
        return json_decode(json_encode($response));
    }
}