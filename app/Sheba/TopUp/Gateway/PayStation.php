<?php

namespace Sheba\TopUp\Gateway;

use App\Models\TopUpOrder;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use Sheba\Dal\TopupOrder\Statuses;
use Sheba\TopUp\Exception\GatewayTimeout;
use Sheba\TopUp\Exception\PayStationInvalidCredentialsException;
use Sheba\TopUp\Exception\PayStationInvalidReferNoException;
use Sheba\TopUp\Exception\PayStationNotWorkingException;
use Sheba\TopUp\Exception\TopUpStillNotResolvedException;
use Sheba\TopUp\Exception\UnknownIpnStatusException;
use Sheba\TopUp\Gateway\Clients\PayStationClient;
use Sheba\TopUp\Gateway\FailedReason\PayStationFailedReason;
use Sheba\TopUp\Gateway\Statuses\PayStationStatuses;
use Sheba\TopUp\Vendor\Response\Ipn\IpnResponse;
use Sheba\TopUp\Vendor\Response\Ipn\PayStation\PayStationEnquiryFailResponse;
use Sheba\TopUp\Vendor\Response\Ipn\PayStation\PayStationEnquirySuccessResponse;
use Sheba\TopUp\Vendor\Response\Ipn\PayStation\PayStationFailResponse;
use Sheba\TopUp\Vendor\Response\Ipn\PayStation\PayStationSuccessResponse;
use Sheba\TopUp\Vendor\Response\PayStationResponse;
use Sheba\TopUp\Vendor\Response\TopUpResponse;
use Sheba\TPProxy\TPProxyClient;
use Sheba\TPProxy\TPProxyServerError;
use Sheba\TPProxy\TPProxyServerTimeout;
use Sheba\TPProxy\TPRequest;

class PayStation implements Gateway, HasIpn
{
    const SHEBA_COMMISSION = 0.0;
    const SUCCESS = 1;
    const FAILED = 2;

//    /** @var TPProxyClient */
//    private $tpClient;

//    private $baseUrl;
//    private $userName;
//    private $password;

    /** @var PayStationClient $payStationClient */
    private $payStationClient;

    public function __construct(PayStationClient $client)
    {
        $this->payStationClient = $client;

//        $this->baseUrl = config('topup.pay_station.base_url');
//        $this->userName = config('topup.pay_station.user_name');
//        $this->password = config('topup.pay_station.password');
    }

    /**
     * @param  TopUpOrder  $topup_order
     * @return TopUpResponse
     * @throws GatewayTimeout
     */
    public function recharge(TopUpOrder $topup_order): TopUpResponse
    {
        return $this->payStationClient->recharge($topup_order);

//        $api_response = $this->call($this->makeUrl($topup_order));
//
//        $response = new PayStationResponse();
//        $response->setResponse($api_response);
//        return $response;
    }

    public function getShebaCommission(): float
    {
        return self::SHEBA_COMMISSION;
    }

    public function getName(): string
    {
        return Names::PAY_STATION;
    }

    /**
     * @param  TopUpOrder  $topup_order
     * @return IpnResponse
     * @throws TPProxyServerError
     * @throws TopUpStillNotResolvedException
     * @throws UnknownIpnStatusException|PayStationInvalidCredentialsException|PayStationInvalidReferNoException
     */
    public function enquire(TopUpOrder $topup_order): IpnResponse
    {
        $api_response = $this->payStationClient->enquiry($topup_order);
        if ($api_response->rsp_code == 5001) {
            throw new PayStationInvalidCredentialsException();
        }

        if ($api_response->rsp_code == 5004) {
            throw new PayStationInvalidReferNoException();
        }

        $status = $api_response->data->status;
        /** @var $ipn_response IpnResponse */
        if ($status == PayStationStatuses::SUCCESS) {
            $ipn_response = app(PayStationEnquirySuccessResponse::class);
        } else {
            if ($status == PayStationStatuses::FAILED) {
                $ipn_response = app(PayStationEnquiryFailResponse::class);
            } else {
                if ($status == PayStationStatuses::PROCESSING) {
                    throw new TopUpStillNotResolvedException($api_response);
                } else {
                    throw new UnknownIpnStatusException();
                }
            }
        }

        $ipn_response->setResponse($api_response);
        return $ipn_response;
    }

    public function getInitialStatus(): string
    {
        return self::getInitialStatusStatically();
    }

    public static function getInitialStatusStatically(): string
    {
        return Statuses::ATTEMPTED;
    }

    public function getFailedReason(): FailedReason
    {
        return new PayStationFailedReason();
    }

//    private function makeUrl(TopUpOrder $topup_order): string
//    {
//        return $this->baseUrl."/external-recharge"
//            ."?ExternalRecharge=Recharge"
//            ."&phone=".$topup_order->payee_mobile
//            ."&operator_type=".$this->getOperatorType($topup_order->vendor_id)
//            ."&amount=".$topup_order->amount
//            ."&recharge_operator_type=".$this->getConnectionType($topup_order->vendor_id, $topup_order->payee_mobile_type)
//            ."&user_name=".$this->userName
//            ."&password=".$this->password
//            ."&ref=".$topup_order->getGatewayRefId();
//    }

//    private function makeUrlForEnquiry(TopUpOrder $topup_order): string
//    {
//        return $this->baseUrl."/number-check"
//            ."?ref=".$topup_order->getGatewayRefId()
//            ."&user_name=".$this->userName
//            ."&password=".$this->password;
//    }

//    private function getOperatorType($vendor_id): string
//    {
//        if ($vendor_id == 2) {
//            return 'RR';
//        }
//        if ($vendor_id == 3) {
//            return 'RA';
//        }
//        if ($vendor_id == 4) {
//            return 'RG';
//        }
//        if ($vendor_id == 5) {
//            return 'RB';
//        }
//        if ($vendor_id == 6) {
//            return 'RT';
//        }
//        if ($vendor_id == 7) {
//            return 'RG';
//        }
//
//        throw new InvalidArgumentException('Invalid operator for pay station topup.');
//    }

//    private function getConnectionType($vendor_id, $connection_type): string
//    {
//        if ($vendor_id == 7) {
//            return "Skitto";
//        }
//        if ($connection_type == "prepaid") {
//            return "Pre-paid";
//        }
//        if ($connection_type == "postpaid") {
//            return "Post-paid";
//        }
//
//        throw new InvalidArgumentException('Invalid connection type for pay station topup.');
//    }

    /**
     * @throws GatewayTimeout
     * @throws PayStationNotWorkingException
     */
//    private function call($url)
//    {
//        $tp_request = (new TPRequest())
//            ->setMethod(TPRequest::METHOD_GET)
//            ->setUrl($url)
//            ->setTimeout(60);
//
//        try {
//            return $this->tpClient->call($tp_request);
//        } catch (TPProxyServerTimeout $e) {
//            throw new GatewayTimeout($e->getMessage());
//        } catch (TPProxyServerError $e) {
//            throw new PayStationNotWorkingException($e->getMessage());
//        }
//    }

    /**
     * @param $request_data
     * @return Application|mixed|IpnResponse
     * @throws UnknownIpnStatusException
     */
    public function buildIpnResponse($request_data)
    {
        if ($request_data['status'] == PayStationStatuses::SUCCESS) {
            return app(PayStationSuccessResponse::class);
        } elseif ($request_data['status'] == PayStationStatuses::FAILED) {
            return app(PayStationFailResponse::class);
        }

        throw new UnknownIpnStatusException();
    }
}