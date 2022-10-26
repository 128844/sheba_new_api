<?php

namespace Sheba\TopUp\Gateway\Clients;

use App\Models\TopUpOrder;
use Exception;
use InvalidArgumentException;
use Sheba\TopUp\Exception\GatewayTimeout;
use Sheba\TopUp\Vendor\Response\PayStationResponse;
use Sheba\TopUp\Vendor\Response\TopUpResponse;
use Sheba\TPProxy\TPProxyClient;
use Sheba\TPProxy\TPProxyServerError;
use Sheba\TPProxy\TPProxyServerTimeout;
use Sheba\TPProxy\TPRequest;

class PayStationClient
{
    /** @var TPProxyClient $tpClient */
    private $tpClient;
    private $baseUrl;
    private $singleTopupUrl;
    private $userName;
    private $password;
    private $authNumber;
    private $topupEnquiryUrl;

    /**
     * PayStationClient constructor.
     *
     * @param  TPProxyClient  $client
     * @param  TPRequest  $request
     */
    public function __construct(TPProxyClient $client, TPRequest $request)
    {
        $this->tpClient = $client;
        $this->tpRequest = $request;

        $this->baseUrl = config('topup.pay_station.base_url');
        $this->topupEnquiryUrl = $this->baseUrl.'/enquiry';
        $this->singleTopupUrl = $this->baseUrl.'/recharge';
        $this->userName = config('topup.pay_station.user_name');
        $this->password = config('topup.pay_station.password');
        $this->authNumber = config('topup.pay_station.auth_number');
    }

    /**
     * @param  TopUpOrder  $topup_order
     * @return TopUpResponse
     * @throws Exception
     * @throws GatewayTimeout
     */
    public function recharge(TopUpOrder $topup_order): TopUpResponse
    {
        $request_data = [
            'refer_no'      => $topup_order->getGatewayRefId(),
            'number'        => formatMobileReverse($topup_order->payee_mobile),
            'amount'        => $topup_order->amount,
            'operator_type' => $this->getOperatorType($topup_order->vendor_id),
            'recharge_type' => $this->getConnectionType($topup_order->payee_mobile_type),
        ];

        $this->tpRequest->setUrl($this->singleTopupUrl)
            ->setMethod(TPRequest::METHOD_POST)
            ->setHeaders($this->getHeaders())
            ->setInput($request_data);

        try {
            $response = $this->tpClient->call($this->tpRequest);
        } catch (TPProxyServerTimeout $e) {
            throw new GatewayTimeout($e->getMessage());
        }

        $topup_response = app(PayStationResponse::class);
        $topup_response->setResponse($response);
        return $topup_response;
    }

    /**
     * @return string[]
     */
    private function getHeaders(): array
    {
        return [
            'username'    => $this->userName,
            'password'    => $this->password,
            'auth_number' => $this->authNumber
        ];
    }

    private function getOperatorType($vendor_id): string
    {
        if ($vendor_id == 2) {
            return 'RR';
        }
        if ($vendor_id == 3) {
            return 'RA';
        }
        if ($vendor_id == 4) {
            return 'RG';
        }
        if ($vendor_id == 5) {
            return 'RB';
        }
        if ($vendor_id == 6) {
            return 'RT';
        }
        if ($vendor_id == 7) {
            return 'SG';
        }

        throw new InvalidArgumentException('Invalid operator for pay station topup.');
    }

    private function getConnectionType($connection_type): string
    {
        if ($connection_type == "prepaid") {
            return "pre-paid";
        }
        if ($connection_type == "postpaid") {
            return "post-paid";
        }

        throw new InvalidArgumentException('Invalid connection type for pay station topup.');
    }

    /**
     * @param  TopUpOrder  $topup_order
     * @return mixed
     * @throws TPProxyServerError
     */
    public function enquiry(TopUpOrder $topup_order)
    {
        $request_data = ['refer_no' => $topup_order->getGatewayRefId()];
        $headers = $this->getHeaders();
        $headers['Content-Type'] = 'application/json';

        $this->tpRequest
            ->setMethod(TPRequest::METHOD_POST)
            ->setUrl($this->topupEnquiryUrl)
            ->setHeaders($headers)
            ->setTimeout(60)
            ->setInput($request_data);

        return $this->tpClient->call($this->tpRequest);
    }
}