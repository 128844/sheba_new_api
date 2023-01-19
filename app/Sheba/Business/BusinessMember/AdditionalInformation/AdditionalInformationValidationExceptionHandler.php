<?php

namespace Sheba\Business\BusinessMember\AdditionalInformation;

use Sheba\Exceptions\Handlers\ValidationExceptionHandler;

class AdditionalInformationValidationExceptionHandler extends ValidationExceptionHandler
{
    /**
     * @return string
     */
    protected function getMessage()
    {
        $exception = $this->exception;
        /** @var AdditionalInformationValidationException $exception */
        return getValidationErrorMessage($exception->getErrors(), "\n");
    }
}
