<?php

namespace Sheba\Exceptions;

use App\Exceptions\HttpException;
use App\Exceptions\DoNotReportException;
use App\Exceptions\WalletTransaction\WalletDebitTransactionForbiddenHandler;
use Exception as BaseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sheba\Business\BusinessMember\AdditionalInformation\AdditionalInformationValidationException;
use Sheba\Business\BusinessMember\AdditionalInformation\AdditionalInformationValidationExceptionHandler;
use Sheba\Exceptions\Exceptions\ExceptionForClient;
use Sheba\Exceptions\Handlers\HttpExceptionHandler;
use Sheba\Exceptions\Handlers\ApiValidationExceptionHandler;
use Sheba\Exceptions\Handlers\ExceptionForClientHandler;
use Sheba\Exceptions\Handlers\Handler;
use Sheba\Exceptions\Handlers\MethodNotAllowedHttpExceptionHandler;
use Sheba\Exceptions\Handlers\NotFoundHttpExceptionHandler;
use Sheba\Exceptions\Handlers\RouteNotFoundExceptionHandler;
use Sheba\Exceptions\Handlers\ThrowableHandler;
use Sheba\Exceptions\Handlers\ValidationExceptionHandler;
use Sheba\Exceptions\Handlers\WrongPinErrorHandler;
use Sheba\OAuth2\WrongPinError;
use Sheba\ShebaPay\Exceptions\ShebaPayException;
use Sheba\ShebaPay\Exceptions\ShebaPayExceptionHandler;
use Sheba\TopUp\Bulk\Exception\InvalidTopupData;
use Sheba\TopUp\Bulk\Exception\InvalidTopupDataHandler;
use Sheba\TopUp\Bulk\Exception\InvalidTotalAmount;
use Sheba\TopUp\Bulk\Exception\InvalidTotalAmountHandler;
use Sheba\TopUp\Exception\PinMismatchException;
use Sheba\TopUp\Exception\PinMismatchExceptionHandler;
use Sheba\Transactions\Wallet\WalletDebitForbiddenException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Throwable;

class HandlerFactory
{
    /**
     * @param Request $request
     * @param BaseException $e
     * @return Handler|null
     */
    public static function get(Request $request, BaseException $e)
    {
        $handler = self::getHandler($e);

        if (is_null($handler)) return null;

        return $handler->setException($e)->setRequest($request);
    }

    /**
     * @param BaseException $e
     * @return Handler
     */
    private static function getHandler(BaseException $e)
    {
        if ($e instanceof ValidationException) return app(ValidationExceptionHandler::class);
        if ($e instanceof AdditionalInformationValidationException) return app(AdditionalInformationValidationExceptionHandler::class);
        if ($e instanceof WrongPinError) return app(WrongPinErrorHandler::class);
        if ($e instanceof PinMismatchException) return app(PinMismatchExceptionHandler::class);
        if ($e instanceof InvalidTotalAmount) return app(InvalidTotalAmountHandler::class);
        if ($e instanceof InvalidTopupData) return app(InvalidTopupDataHandler::class);
        if ($e instanceof WalletDebitForbiddenException) return app(WalletDebitTransactionForbiddenHandler::class);
        if ($e instanceof DoNotReportException) return app(ApiValidationExceptionHandler::class);
        if ($e instanceof MethodNotAllowedHttpException) return app(MethodNotAllowedHttpExceptionHandler::class);
        if ($e instanceof NotFoundHttpException) return app(NotFoundHttpExceptionHandler::class);
        if ($e instanceof RouteNotFoundException) return app(RouteNotFoundExceptionHandler::class);
        if ($e instanceof HttpException) return app(HttpExceptionHandler::class);
        if ($e instanceof ExceptionForClient) return app(ExceptionForClientHandler::class);
        if ($e instanceof ShebaPayException) return app(ShebaPayExceptionHandler::class);
        if ($e instanceof Throwable) return app(ThrowableHandler::class);
        return null;
    }
}
