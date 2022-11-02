<?php

namespace App\Sheba\Payment\Methods\AamarPay\Response;

use Sheba\Payment\Methods\Response\PaymentMethodErrorResponse;
use Sheba\Payment\Methods\Response\PaymentMethodSuccessResponse;

class InitResponse extends \Sheba\Payment\Methods\Response\PaymentMethodResponse
{

    public function hasSuccess()
    {
        if(config('app.env') === 'production' && substr($this->response, 0, 27) === '/paynow_check_update.php?d=') {
            return true;
        } elseif (config('app.env') !== 'production' && substr( $this->response, 0, 18 ) === '/paynow.php?track=') {
            return true;
        }
        return false;
    }

    public function getSuccess(): PaymentMethodSuccessResponse
    {
        $success = new PaymentMethodSuccessResponse();
        if (config('app.env') === 'production') {
            $success->id = substr($this->response, 27);
        } else {
            $success->id = substr($this->response, 18);
        }
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