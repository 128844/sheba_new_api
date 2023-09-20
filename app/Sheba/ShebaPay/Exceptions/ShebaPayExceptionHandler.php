<?php namespace Sheba\ShebaPay\Exceptions;

use Illuminate\Http\JsonResponse;
use Sheba\Exceptions\Handlers\GenericHandler;
use Sheba\Exceptions\Handlers\Handler;

class ShebaPayExceptionHandler extends Handler
{
    use GenericHandler;

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->getCode()], $this->getCode());
    }

}