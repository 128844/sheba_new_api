<?php namespace Sheba\TopUp\FailedReason;

use Throwable;

class AirtelFailedReason extends PretupsFailedReason
{
    public function getReason()
    {
        try {
            $transaction_details = json_decode($this->transaction, true);
            if ($transaction_details_response = $transaction_details['response']) {
                return $transaction_details_response['MESSAGE'];
            }
            return $transaction_details['message'];
        } catch (Throwable $e) {
            logError($e);
        }

        return "The Recharge could not be processed due to a technical issue. Pls try again later.";
    }
}