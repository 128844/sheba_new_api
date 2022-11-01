<?php namespace Sheba\TopUp\Vendor\Response\Ipn\PayStation;

use Sheba\TopUp\Vendor\Response\Ipn\SuccessResponse;

class PayStationEnquirySuccessResponse extends SuccessResponse
{
    use PayStationEnquiryResponse;

    /**
     * @return string | null
     */
    public function getUpdatedTransactionId()
    {
        return $this->response['data']['trxId'];
    }
}