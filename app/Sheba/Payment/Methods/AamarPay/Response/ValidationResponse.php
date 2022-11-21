<?php

namespace App\Sheba\Payment\Methods\AamarPay\Response;

use Sheba\Payment\Methods\Response\PaymentMethodErrorResponse;
use Sheba\Payment\Methods\Response\PaymentMethodSuccessResponse;

class ValidationResponse extends \Sheba\Payment\Methods\Response\PaymentMethodResponse
{
    const SUCCESS_CODE=2;

    public function hasSuccess()
    {
        return $this->response
            && $this->response->mer_txnid === $this->payment->transaction_id
            && $this->response->status_code == self::SUCCESS_CODE;
    }

    public function getSuccess(): PaymentMethodSuccessResponse
    {
        $success = new PaymentMethodSuccessResponse();
        $success->id = $this->response->pg_txnid;
        $success->details = $this->response;
        return $success;
    }

    public function getError(): PaymentMethodErrorResponse
    {
        $error = new PaymentMethodErrorResponse();
        $error->id = $this->response->pg_txnid ?? null;
        $error->details = $this->response;
        return $error;
    }
}