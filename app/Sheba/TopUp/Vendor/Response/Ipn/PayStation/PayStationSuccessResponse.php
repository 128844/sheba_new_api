<?php namespace Sheba\TopUp\Vendor\Response\Ipn\PayStation;

use Sheba\TopUp\Vendor\Response\Ipn\SuccessResponse;

class PayStationSuccessResponse extends SuccessResponse
{
    use PayStationIpnResponse;

    /**
     * @return string | null
     */
    public function getUpdatedTransactionId()
    {
        return $this->response['trxId'];
    }
}