<?php

namespace App\Sheba\Payment\Methods\AamarPay\Response;

use Sheba\Payment\Methods\Response\PaymentMethodErrorResponse;
use Sheba\Payment\Methods\Response\PaymentMethodSuccessResponse;

class InitResponse extends \Sheba\Payment\Methods\Response\PaymentMethodResponse
{

    public function hasSuccess()
    {
        if(substr( $this->response, 0, 18 ) === "/paynow.php?track=") {
            return true;
        }
        return false;
    }

    public function getSuccess(): PaymentMethodSuccessResponse
    {
        $success = new PaymentMethodSuccessResponse();
        $success->id = substr($this->response, 18);
        $success->details = $this->response;
        $success->redirect_url = config('payment.aamarpay.base_url') . $this->response;
        return $success;
    }

    public function getError(): PaymentMethodErrorResponse
    {
        $error = new PaymentMethodErrorResponse();
        $error->id = null;
        $error->details = $this->response;
        $error->message = $this->response;
        return $error;
    }
}