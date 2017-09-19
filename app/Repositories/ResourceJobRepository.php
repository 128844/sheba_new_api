<?php

namespace App\Repositories;


use App\Models\PartnerOrder;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;

class ResourceJobRepository
{

    public function rearrange($jobs)
    {
        $process_job = $jobs->where('status', 'Process')->values()->all();
        $served_jobs = $this->_getLastServedJobOfPartnerOrder($jobs->where('status', 'Served')->values()->all());
        $other_jobs = $jobs->filter(function ($job) {
            return $job->status != 'Process' && $job->status != 'Served';
        });
        $other_jobs = $other_jobs->map(function ($item) {
            return array_add($item, 'preferred_time_priority', constants('JOB_PREFERRED_TIMES_PRIORITY')[$item->preferred_time]);
        });
        $other_jobs = $other_jobs->sortBy(function ($job) {
            return sprintf('%-12s%s', $job->schedule_date, $job->preferred_time_priority);
        })->values()->all();
        $jobs = array_merge($served_jobs, array_merge($process_job, $other_jobs));
        return $jobs;
    }

    public function getJobs($resource)
    {
        $resource->load(['jobs' => function ($q) {
            $q->select('id', 'resource_id', 'schedule_date', 'preferred_time', 'service_name', 'status', 'partner_order_id')
                ->where('schedule_date', '<=', date('Y-m-d'))->whereIn('status', ['Accepted', 'Served', 'Process', 'Schedule Due'])
                ->with('partner_order.order');
        }]);
        return $resource->jobs;
    }

    private function _getLastServedJobOfPartnerOrder($jobs)
    {
//        $final = [];
//        foreach ($jobs as $job) {
//            $partner_order_jobs = $job->partner_order->jobs->map(function ($item) {
//                return array_add($item, 'preferred_time_priority', constants('JOB_PREFERRED_TIMES_PRIORITY')[$item->preferred_time]);
//            });
//            $last_job = $partner_order_jobs->sortBy(function ($job) {
//                return sprintf('%-12s%s', $job->schedule_date, $job->preferred_time_priority);
//            })->last();
//            $partner_order = $job->partner_order;
//            $partner_order->calculate();
//            if ($last_job->id == $job->id && $partner_order->due != 0) {
//                array_push($final, $job);
//            }
//        }
//        return $final;
//
        $final_last_jobs = [];
        foreach ($jobs as $job) {
            $partner_order = $job->partner_order;
            $partner_order->calculate();
            $all_jobs_of_this_partner_order = $job->partner_order->jobs;
            $partner_order_other_jobs = $all_jobs_of_this_partner_order->reject(function ($item, $key) use ($job) {
                return $item->id == $job->id;
            });
            //partner order has due
            if ($partner_order->due > 0) {
                //only one served job in partner order
                if ($partner_order_other_jobs->count() == 0) {
                    array_push($final, $job);
                } //all other jobs are served. Then check if job is the last job of partner order
                else if ($partner_order_other_jobs->where('status', 'Served')->count() == $partner_order_other_jobs->count()) {
                    $last_job = ($all_jobs_of_this_partner_order->sortBy('delivered_date'))->last();
                    if ($last_job->id == $job->id) {
                        array_push($final_last_jobs, $job);
                    }
                }
            }

        }
        return $final_last_jobs;
    }

    public function addJobInformationForAPI($jobs)
    {
        foreach ($jobs as $job) {
            $job['delivery_name'] = $job->partner_order->order->delivery_name;
            $job['delivery_mobile'] = $job->partner_order->order->delivery_mobile;
            $job['delivery_address'] = $job->partner_order->order->delivery_address;
            $job['code'] = $job->code();
            $this->_stripUnwantedInformationForAPI($job);
        }
        return $jobs;
    }

    private function _stripUnwantedInformationForAPI($job)
    {
        array_forget($job, 'partner_order');
        array_forget($job, 'partner_order_id');
        array_forget($job, 'resource_id');
        return $job;
    }

    public function changeStatus($job, $request)
    {
        try {
            $client = new Client();
            $res = $client->request('POST', env('SHEBA_BACKEND_URL') . '/api/job/' . $job . '/change-status',
                [
                    'form_params' => [
                        'resource_id' => $request->resource->id,
                        'remember_token' => $request->resource->remember_token,
                        'status' => $request->status
                    ]
                ]);
            return json_decode($res->getBody());
        } catch (RequestException $e) {
            return false;
        }
    }

    public function reschedule($job, $request)
    {
        try {
            $client = new Client();
            $res = $client->request('POST', env('SHEBA_BACKEND_URL') . '/api/job/' . $job . '/reschedule',
                [
                    'form_params' => [
                        'resource_id' => $request->resource->id,
                        'remember_token' => $request->resource->remember_token,
                        'schedule_date' => $request->schedule_date,
                        'preferred_time' => $request->preferred_time,
                    ]
                ]);
            return json_decode($res->getBody());
        } catch (RequestException $e) {
            return false;
        }
    }

    public function collectMoney(PartnerOrder $order, Request $request)
    {
        try {
            $client = new Client();
            $res = $client->request('POST', env('SHEBA_BACKEND_URL') . '/api/partner-order/' . $order->id . '/collect',
                [
                    'form_params' => [
                        'resource_id' => $request->resource->id,
                        'remember_token' => $request->resource->remember_token,
                        'partner_collection' => $request->amount,
                    ]
                ]);
            return json_decode($res->getBody());
        } catch (RequestException $e) {
            return false;
        }
    }

    public function calculateActionsForThisJob($first_job_from_list, $job)
    {
        if ($job->status == 'Served') {
            if ($first_job_from_list->status == 'Served' && $job->id == $first_job_from_list->id) {
                $job['can_collect'] = true;
                $partner_order = $job->partner_order;
                $partner_order->calculate();
                $job['collect_money'] = (double)$partner_order->due;
                array_forget($job, 'partner_order');
            }
        } elseif ($job->status == 'Process') {
            if ($first_job_from_list->status == 'Process' && $job->id == $first_job_from_list->id) {
                $job['can_serve'] = true;
            }
        } else {
            if ($first_job_from_list->status != 'Process' && $first_job_from_list->status != 'Served') {
                $job['can_process'] = true;
            }
        }
        return $job;
    }
}