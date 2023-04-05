<?php namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IpWhitelistMiddlewareForMTB
{

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $whitelisted_ips = ["124.109.106.244"];
        if (!in_array(getIp(), $whitelisted_ips)) {
            return response('', 403);
        }

        return $next($request);
    }
}