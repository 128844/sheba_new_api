<?php

namespace App\Sheba\Transactions\Wallet;

use App\Exceptions\DoNotReportException;
use Throwable;

class WalletUnexpectedException extends DoNotReportException
{
    public function __construct($message = "", $code = 406, Throwable $previous = null)
    {
        if (empty($message)) {
            $message = 'System Error on Wallet Transaction';
        }

        parent::__construct($message, $code, $previous);
    }
}