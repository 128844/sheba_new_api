<?php

namespace Sheba\TopUp\Exception;

use Exception;
use Throwable;

class PayStationInvalidCredentialsException extends Exception
{
    public function __construct($message = "Pay Station Invalid Credential", $code = 500, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
