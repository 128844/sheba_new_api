<?php

namespace Sheba\ShebaPay\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Sheba\ShebaPay\Exceptions\UnauthorisedRequestException;

class ShebaPayBasicAuthMiddleware
{
    private $username, $password;

    public function __construct()
    {
        $this->username = config('sheba_pay.auth.username');
        $this->password = config('sheba_pay.auth.password');
    }

    /**
     * @throws UnauthorisedRequestException
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$request->hasHeader('Authorization') && (!$request->hasHeader('username') || !$request->hasHeader('password'))) {
            throw new UnauthorisedRequestException();
        }
        $username = $request->getUser() ?? $request->header('username');
        $password = $request->getPassword() ?? $request->header('password');
        if ($username !== $this->username && $password !== $this->password) {
            throw new UnauthorisedRequestException("Invalid Credentials Provided!");
        }
        return $next($request);
    }

}