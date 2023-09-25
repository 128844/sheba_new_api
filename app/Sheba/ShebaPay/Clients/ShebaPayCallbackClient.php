<?php

namespace Sheba\ShebaPay\Clients;

use App\Models\TopUpOrder;
use GuzzleHttp\Client;
use Sheba\ShebaPay\Helpers\OrderData;
use Sheba\TopUp\TopUpFailedReason;

class ShebaPayCallbackClient
{
    /**
     * @var TopUpOrder
     */
    private $order;
    /**
     * @var Client
     */
    private $client;

    public function __construct(TopUpOrder $order)
    {
        $order->reload();
        $this->order = $order;
        $this->client = new Client();
    }

    public function call()
    {
        $res = $this->client->get($this->order->shebaPayTransaction->callback_url, ['params' => (new OrderData($this->order))->get(), 'http_errors' => false]);
        $this->updateCallbackResponse($res);
    }

    private function updateCallbackResponse($res)
    {

        $response = decodeGuzzleResponse($res);
        if (is_array($response)) {
            $response['request_status_code'] = $res->getStatusCode();
        } else {
            $response = ['request_status_code' => $res->getStatusCode(), 'res' => $res->getBody()->getContents()];
        }
        $this->order->shebaPayTransaction->callback_result = json_encode($response);
        $this->order->shebaPayTransaction->save();
        $this->order->is_agent_debited = 1;
        $this->order->save();
    }


}