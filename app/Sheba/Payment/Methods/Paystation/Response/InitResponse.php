<?php

namespace Sheba\Payment\Methods\Paystation\Response;

use Sheba\Payment\Methods\Response\PaymentMethodErrorResponse;
use Sheba\Payment\Methods\Response\PaymentMethodResponse;
use Sheba\Payment\Methods\Response\PaymentMethodSuccessResponse;

class InitResponse extends PaymentMethodResponse
{
    public function hasSuccess()
    {
        return $this->response->status_code == "200";
    }

    public function getSuccess(): PaymentMethodSuccessResponse
    {
        $success = new PaymentMethodSuccessResponse();
        $success->id = null;
        $success->details = $this->response;
        $success->redirect_url = $this->response->payment_url;
        return $success;
    }

    public function getError(): PaymentMethodErrorResponse
    {
        $error = new PaymentMethodErrorResponse();
        $error->id = null;
        $error->details = $this->response;
        return $error;
    }
}
