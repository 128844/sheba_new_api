<?php

namespace Sheba\TopUp\Exception;

use Exception;
use Throwable;

class PayStationInvalidReferNoException extends Exception
{
    public function __construct($message = "Pay Station Invalid Refer No", $code = 500, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
