<?php namespace Sheba\TopUp\FailedReason;

abstract class FailedReason
{
    protected $transaction;

    public function setTransaction($transaction)
    {
        $this->transaction = $transaction;
        return $this;
    }

    public abstract function getReason();
}