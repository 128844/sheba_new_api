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
        return $this->response && $this->response->status_code == "2000" && $this->response->status == "Success";
    }

    /**
     * @inheritDoc
     */
    public function getTransactionId()
    {
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
        return $this->response->message;
    }

    public function resolveTopUpSuccessStatus(): string
    {
        return PayStation::getInitialStatusStatically();
    }

    public function isPending(): bool
    {
        return $this->response && $this->response->status_code == "2000" && $this->response->status == "Accepted";
    }
}