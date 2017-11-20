<?php

namespace App\Http\Middleware;

use App\Models\Job;
use Closure;

class PartnerJobAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $partner = $request->partner;
        $job = Job::select('id', 'status', 'resource_id', 'partner_order_id')->where('id', $request->job)->first();
        if (!$job) {
            return api_response($request, null, 404);
        }
        if ($job->partner_order->partner->id != $partner->id || in_array($job->status, [constants('JOB_STATUSES')['Served'], constants('JOB_STATUSES')['Cancelled']])) {
            return api_response($request, null, 403);
        }
        $request->merge(['job' => $job]);
        return $next($request);
    }
}
