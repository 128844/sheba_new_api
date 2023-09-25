<?php

namespace Sheba\ShebaPay\Clients;

use App\Models\TopUpOrder;
use GuzzleHttp\Client;
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
        $this->order = $order;
        $this->client = new Client();
        $this->order->reload();
    }

    public function call()
    {
        $res = $this->client->get( $this->order->shebaPayTransaction->callback_url, ['params' => $this->getParams(), 'http_errors' => false]);
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
        $this->order->shebaPayTransaction->update(['callback_result' => json_decode($response)]);
    }


    private function getParams(): array
    {
        return [

            'payee_mobile' => $this->order->payee_mobile,
            'payee_name' => $this->order->payee_name ?: 'N/A',
            'amount' => $this->order->amount,
            'operator' => $this->order->vendor->name,
            'status' => $this->order->getStatusForAgent(),
            'transaction_id' => $this->order->transaction_id,
            'payee_mobile_type' => $this->order->payee_mobile_type,
            'failed_reason' => (new TopUpFailedReason())->setTopup($this->order)->getFailedReason(),
            'created_at' => $this->order->created_at->format('jS M, Y h:i A'),
            'created_at_raw' => $this->order->created_at->format('Y-m-d H:i:s')
        ];
    }

}