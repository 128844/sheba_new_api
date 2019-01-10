<?php

namespace App\Http\Middleware;

use App\Models\Affiliate;
use App\Models\Customer;
use App\Models\Resource;
use App\Repositories\ProfileRepository;
use Closure;
use ErrorException;

class ProfileAuthMiddleware
{
    private $profileRepo;

    public function __construct()
    {
        $this->profileRepo = new ProfileRepository();
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->has('remember_token')) {
            if ($request->has('from')) {
                $from = $this->profileRepo->getAvatar($request->from);
                $avatar = null;
                if ($from == 'customer') {
                    $avatar = Customer::where('remember_token', $request->input('remember_token'))->first();
                } elseif ($from == 'affiliate') {
                    $avatar = Affiliate::where('remember_token', $request->input('remember_token'))->first();
                } elseif ($from == 'resource') {
                    $avatar = Resource::where('remember_token', $request->input('remember_token'))->first();
                }
                if ($avatar != null) {
                    if ($avatar->id == $request->id) {
                        $request->merge(['profile' => $avatar->profile]);
                        return $next($request);
                    } else {
                        return api_response($request, null, 403, ["message" => "You're not authorized to access this user."]);
                    }
                } else {
                    return api_response($request, null, 404, ["message" => "User not found."]);
                }
            }
        } else {
            return api_response($request, null, 400, ["message" => "Authentication token is missing from the request."]);
        }
    }
}
