<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\JobCancelLog;
use App\Repositories\JobCancelLogRepository;
use App\Repositories\PapRepository;
use App\Sheba\JobStatus;
use FacebookAds\Http\Exception\RequestException;
use GuzzleHttp\Client;
use function GuzzleHttp\Promise\all;
use Illuminate\Http\Request;
use DB;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class JobController extends Controller
{
    private $job_statuses_show;
    private $job_statuses;

    public function __construct()
    {
        $this->job_statuses_show = config('constants.JOB_STATUSES_SHOW');
        $this->job_statuses = config('constants.JOB_STATUSES');
    }

    public function index(Request $request)
    {
        try {
            $this->validate($request, [
                'filter' => 'required|string|in:ongoing,history'
            ]);
            $filter = $request->filter;
            $customer = $request->customer->load(['orders' => function ($q) use ($filter) {
                $q->with(['partnerOrders' => function ($q) use ($filter) {
                    $q->$filter()->with(['jobs' => function ($q) {
                        $q->with(['resource.profile', 'category']);
                    }]);
                }]);
            }]);
            $all_jobs = $this->getJobOfOrders($customer->orders->filter(function ($order) {
                return $order->partnerOrders->count() > 0;
            }))->sortByDesc('created_at');
            return api_response($request, $all_jobs, 200, ['orders' => $all_jobs->values()->all()]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            return api_response($request, null, 500);
        }
    }

    public function show($customer, $job, Request $request)
    {
        try {
            $customer = $request->customer;
            $job = $request->job;
            if (count($job->jobServices) == 0) {

            }
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            return api_response($request, null, 500);
        }
    }

    private function getJobOfOrders($orders)
    {
        $all_jobs = collect();
        foreach ($orders as $order) {
            foreach ($order->partnerOrders as $partnerOrder) {
                $partnerOrder->calculateStatus();
                foreach ($partnerOrder->jobs as $job) {
                    $category = $job->category == null ? $job->service->category : $job->category;
                    $all_jobs->push(collect(array(
                        'job_id' => $job->id,
                        'category_name' => $category->name,
                        'schedule_date' => $job->schedule_date ? $job->schedule_date : null,
                        'preferred_time' => $job->preferred_time ? $job->preferred_time : null,
                        'status' => $partnerOrder->status,
                        'order_code' => $order->code(),
                        'created_at' => $job->created_at->format('Y-m-d'),
                        'created_at_timestamp' => $job->created_at->timestamp
                    )));
                }
            }
        }
        return $all_jobs;
    }

    public function getInfo($customer, $job, Request $request)
    {
        $job = Job::find($job);
        if ($job != null) {
            if ($job->partner_order->order->customer_id == $customer) {
                $job = Job::with(['partner_order' => function ($query) {
                    $query->select('id', 'partner_id', 'order_id')->with(['partner' => function ($query) {
                        $query->select('id', 'name');
                    }])->with(['order' => function ($query) {
                        $query->select('id');
                    }]);
                }])->with(['resource' => function ($q) {
                    $q->select('id', 'profile_id')->with(['profile' => function ($q) {
                        $q->select('id', 'name', 'mobile', 'pro_pic');
                    }]);
                }])->with(['usedMaterials' => function ($query) {
                    $query->select('id', 'job_id', 'material_name', 'material_price');
                }])->with(['service' => function ($query) {
                    $query->select('id', 'name', 'unit');
                }])->with(['review' => function ($query) {
                    $query->select('job_id', 'review_title', 'review', 'rating');
                }])->where('id', $job->id)
                    ->select('id', 'service_id', 'resource_id', DB::raw('DATE_FORMAT(schedule_date, "%M %d, %Y") as schedule_date'), DB::raw('DATE_FORMAT(delivered_date, "%M %d, %Y at %h:%i %p") as delivered_date'), 'created_at', 'preferred_time', 'service_name', 'service_quantity', 'service_variable_type', 'service_variables', 'job_additional_info', 'service_option', 'discount', 'status', 'service_unit_price', 'partner_order_id')
                    ->first();
                array_add($job, 'status_show', $this->job_statuses_show[array_search($job->status, $this->job_statuses)]);

                $job_model = Job::find($job->id);
                $job_model->calculate();
                array_add($job, 'material_price', $job_model->materialPrice);
                array_add($job, 'total_cost', $job_model->grossPrice);
                array_add($job, 'job_code', $job_model->fullCode());
                array_add($job, 'time', $job->created_at->format('jS M, Y'));
                array_forget($job, 'created_at');
                array_add($job, 'service_price', $job_model->servicePrice);
                if ($job->resource != null) {
                    $profile = $job->resource->profile;
                    array_forget($job, 'resource');
                    $job['resource'] = $profile;
                } else {
                    $job['resource'] = null;
                }

                return response()->json(['job' => $job, 'msg' => 'successful', 'code' => 200]);
            } else {
                return response()->json(['msg' => 'unauthorized', 'code' => 409]);
            }
        } else {
            return api_response($request, null, 404);
        }
    }

    public function getPreferredTimes()
    {
        return response()->json(['times' => config('constants.JOB_PREFERRED_TIMES'), 'valid_times' => $this->getSelectableTimes(), 'code' => 200]);
    }

    private function getSelectableTimes()
    {
        $today_slots = [];
        foreach (constants('JOB_PREFERRED_TIMES') as $time) {
            if ($time == "Anytime" || Carbon::now()->lte(Carbon::createFromTimestamp(strtotime(explode(' - ', $time)[1])))) {
                $today_slots[$time] = $time;
            }
        }
        return $today_slots;
    }

    public function cancelJobReasons()
    {
        return response()->json(['reasons' => config('constants.JOB_CANCEL_REASONS_FROM_CUSTOMER'), 'code' => 200]);
    }

    public function cancel($customer, $job, Request $request)
    {
        try {
            $job = Job::find($job);
            $previous_status = $job->status;
            $customer = $request->customer;
            $job_status = new JobStatus($job, $request);
            $job_status->__set('updated_by', $request->customer);
            if ($response = $job_status->update('Cancelled')) {
                $job_cancel_log = new JobCancelLogRepository($job);
                $job_cancel_log->__set('created_by', $customer);
                $job_cancel_log->store($previous_status, $request->reason);
                return api_response($request, true, 200);
            } else {
                return api_response($request, $response, $response->code);
            }
        } catch (\Throwable $e) {
            return api_response($request, null, 500);
        }
    }

}
