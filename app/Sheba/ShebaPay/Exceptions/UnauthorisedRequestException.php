<?php

namespace Sheba\ShebaPay\Exceptions;

use Exception;
use Throwable;

class UnauthorisedRequestException extends Exception
{
    public function __construct($message = "Unauthorised Request", $code = 401, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}