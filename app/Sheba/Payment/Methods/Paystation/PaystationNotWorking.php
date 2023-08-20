<?php

namespace Sheba\Payment\Methods\Paystation;

use Exception;
use Throwable;

class PaystationNotWorking extends Exception
{
    public function __construct($message = "Paystation Not Working", $code = 500, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
