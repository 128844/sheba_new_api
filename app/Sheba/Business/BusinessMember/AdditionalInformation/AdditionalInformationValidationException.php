<?php

namespace Sheba\Business\BusinessMember\AdditionalInformation;

use Exception;

class AdditionalInformationValidationException extends Exception
{
    private $errors;

    public function __construct($errors)
    {
        parent::__construct("The given data is not correct");
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
