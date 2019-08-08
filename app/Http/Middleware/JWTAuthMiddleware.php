<?php namespace App\Http\Middleware;


use Closure;
use Sheba\Auth\Auth;
use Sheba\Auth\JWTAuth;

class JWTAuthMiddleware
{
    public $auth;
    public $request;
    public $JWTAuth;

    public function __construct(Auth $auth, JWTAuth $jwt_auth)
    {
        $this->auth = $auth;
        $this->JWTAuth = $jwt_auth;
        $this->auth->setStrategy($this->JWTAuth)->setRequest(\request());
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($user = $this->auth->authenticate()) {
            $type = strtolower(class_basename($user));
            $request->merge([$type => $user, 'type' => $type, 'user' => $user]);
            return $next($request);
        } else {
            return api_response($request, null, 403, ["message" => "You're not authorized to access this user."]);
        }
    }
}