<?php

namespace Sheba\ShebaPay\Exceptions;

use Exception;
use Throwable;

class ShebaPayException extends Exception
{
    public function __construct($message = "Sheba Pay Request Exception", $code = 500, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}