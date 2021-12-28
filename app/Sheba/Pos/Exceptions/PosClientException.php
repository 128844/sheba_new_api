<?php

namespace App\Sheba\Pos\Exceptions;

use Exception;
use Throwable;

class PosClientException extends Exception
{
    public function __construct($message = "", $code = 500, Throwable $previous = null)
    {
        if (!$message || $message == "") {
            $message = 'Expense Tracking server not working as expected.';
        }
        parent::__construct($message, $code, $previous);
    }
}