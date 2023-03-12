<?php

namespace App\Sheba\Customer\Exception;

use Throwable;

class CustomerDeleteException extends \Exception
{
    public function __construct($message = "Customer Can not be deleted", $code = 400, Throwable $previous = null) { parent::__construct($message, $code, $previous); }

}