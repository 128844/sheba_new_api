<?php

namespace Sheba\Payment\Methods\Paystation\Response;


use Sheba\Payment\Methods\Response\PaymentMethodErrorResponse;
use Sheba\Payment\Methods\Response\PaymentMethodResponse;
use Sheba\Payment\Methods\Response\PaymentMethodSuccessResponse;

class ValidateResponse extends PaymentMethodResponse
{
    public function hasSuccess()
    {
        return $this->response->status_code == "200" && $this->getTrxStatus() == "Success";
    }

    public function getSuccess(): PaymentMethodSuccessResponse
    {
        $success = new PaymentMethodSuccessResponse();
        $success->id = $this->getTrxId();
        $success->details = $this->response;
        return $success;
    }

    public function getError(): PaymentMethodErrorResponse
    {
        $error = new PaymentMethodErrorResponse();
        $error->id = $this->getTrxId();
        $error->details = $this->response;
        return $error;
    }

    private function getTrxStatus()
    {
        if (!$this->hasData()) return 'Unknown';

        return $this->response->data->trx_status;
    }

    private function hasData()
    {
        return isset($this->response->data);
    }

    private function getTrxId()
    {
        if (!$this->hasData()) return null;

        return $this->response->data->trx_id;
    }
}
