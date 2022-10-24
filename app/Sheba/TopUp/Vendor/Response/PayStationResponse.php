<?php namespace Sheba\TopUp\Vendor\Response;

use Sheba\TopUp\Gateway\PayStation;

class PayStationResponse extends TopUpResponse
{
    /**
     * @inheritDoc
     */
    public function hasSuccess(): bool
    {
        return $this->isCompleted() || $this->isPending();
    }

    public function isCompleted(): bool
    {
        // return $this->response && $this->response->Status == "SUCCESS";
        return $this->response && $this->response->status_code == "2000";
    }

    /**
     * @inheritDoc
     */
    public function getTransactionId()
    {
        // return $this->response->Transiction_id;
        return $this->response->trxId;
    }

    /**
     * @inheritDoc
     */
    public function getErrorCode()
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getErrorMessage(): string
    {
        return $this->response->Message;
    }

    public function resolveTopUpSuccessStatus(): string
    {
        return PayStation::getInitialStatusStatically();
    }

    public function isPending(): bool
    {
        return $this->response && $this->response->Status == "REQUEST ACCEPTED";
    }
}