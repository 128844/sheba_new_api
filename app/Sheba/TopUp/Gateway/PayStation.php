<?php

namespace Sheba\TopUp\Gateway;

use App\Models\TopUpOrder;
use Illuminate\Foundation\Application;
use Sheba\Dal\TopupOrder\Statuses;
use Sheba\TopUp\Exception\GatewayTimeout;
use Sheba\TopUp\Exception\PayStationInvalidCredentialsException;
use Sheba\TopUp\Exception\PayStationInvalidReferNoException;
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
use Sheba\TopUp\Vendor\Response\TopUpResponse;
use Sheba\TPProxy\TPProxyServerError;

class PayStation implements Gateway, HasIpn
{
    const SHEBA_COMMISSION = 0.0;
    const SUCCESS = 1;
    const FAILED = 2;
    /** @var PayStationClient $payStationClient */
    private $payStationClient;

    public function __construct(PayStationClient $client)
    {
        $this->payStationClient = $client;
    }

    /**
     * @param  TopUpOrder  $topup_order
     * @return TopUpResponse
     * @throws GatewayTimeout
     */
    public function recharge(TopUpOrder $topup_order): TopUpResponse
    {
        return $this->payStationClient->recharge($topup_order);
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