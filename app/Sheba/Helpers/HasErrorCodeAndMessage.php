<?php namespace Sheba\Helpers;

trait HasErrorCodeAndMessage
{
    private $errorCode = null;
    private $errorMessage = null;

    public function hasError()
    {
        return !is_null($this->errorCode);
    }

    public function hasNoError()
    {
        return !$this->hasError();
    }

    public function getErrorCode()
    {
        return $this->errorCode;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    private function setError($code, $message = null)
    {
        $this->errorCode = $code;
        if ($message) $this->errorMessage = $message;
    }
}
