<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Repositories\CommentRepository;
use App\Repositories\PartnerJobRepository;
use App\Repositories\PartnerOrderRepository;
use App\Repositories\PartnerRepository;
use App\Repositories\ResourceJobRepository;
use App\Sheba\Logs\OrderLogs;
use App\Sheba\Logs\PartnerOrderLogs;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Sheba\Logs\JobLogs;
use Validator;

class PartnerOrderController extends Controller
{
    private $partnerOrderRepository;
    private $partnerJobRepository;

    public function __construct()
    {
        $this->partnerOrderRepository = new PartnerOrderRepository();
        $this->partnerJobRepository = new PartnerJobRepository();
    }

    public function show($partner, Request $request)
    {
        try {
            $this->validate($request, [
                'status' => 'sometimes|bail|required|string',
                'filter' => 'sometimes|bail|required|string|in:ongoing,history'
            ]);
            $partner_order = $this->partnerOrderRepository->getOrderDetails($request);
            $partner_order['version'] = 'v1';
            return api_response($request, $partner_order, 200, ['order' => $partner_order]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            return api_response($request, null, 500);
        }
    }

    public function showV2($partner, Request $request)
    {
        try {
            $this->validate($request, [
                'status' => 'sometimes|bail|required|string',
                'filter' => 'sometimes|bail|required|string|in:ongoing,history'
            ]);
            $partner_order = $this->partnerOrderRepository->getOrderDetailsV2($request);
            $partner_order['version'] = 'v2';
            return api_response($request, $partner_order, 200, ['order' => $partner_order]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            return api_response($request, null, 500);
        }
    }

    public function newOrders($partner, Request $request)
    {
        try {
            $this->validate($request, ['sort' => 'sometimes|required|string|in:created_at,created_at:asc,created_at:desc,schedule_date,schedule_date:asc,schedule_date:desc']);
            $orders = $this->partnerOrderRepository->getNewOrdersWithJobs($request);
            return count($orders) > 0 ? api_response($request, $orders, 200, ['orders' => $orders]) : api_response($request, null, 404);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            return api_response($request, null, 500);
        }
    }

    public function getOrders($partner, Request $request)
    {
        try {
            $this->validate($request, ['sort' => 'sometimes|required|string|in:created_at,created_at:asc,created_at:desc', 'filter' => 'required|in:ongoing,history']);
            $partner_orders = $this->partnerOrderRepository->getOrders($request);
            return count($partner_orders) > 0 ? api_response($request, $partner_orders, 200, ['orders' => $partner_orders]) : api_response($request, null, 404);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            return api_response($request, null, 500);
        }
    }

    public function getBills($partner, Request $request)
    {
        try {
            $partner_order = $request->partner_order;
            $partner_order->calculate(true);
            $partner_order = array(
                'id' => $partner_order->id,
                'service_price' => (double)$partner_order->totalServicePrice,
                'material_price' => (double)$partner_order->totalMaterialPrice,
                'discount' => (double)$partner_order->totalDiscount,
                'total_price' => (double)$partner_order->totalPrice,
                'paid' => (double)$partner_order->paid,
                'due' => (double)$partner_order->due,
                'invoice' => $partner_order->invoice
            );
            return api_response($request, $partner_order, 200, ['order' => $partner_order]);
        } catch (\Throwable $e) {
            return api_response($request, null, 500);
        }
    }

    public function getLogs($partner, Request $request)
    {
        try {
            $all_logs = collect();
            $this->formatLogs((new PartnerOrderLogs($request->partner_order))->all(), $all_logs);
            $this->formatLogs((new OrderLogs($request->partner_order->order))->all(), $all_logs);
            foreach ($request->partner_order->jobs as $job) {
                $job_logs = (new JobLogs($job))->all();
                $this->formatLogs($job_logs, $all_logs);
            }
            $dates = $all_logs->groupBy('created_at')->sortByDesc(function ($item, $key) {
                return $key;
            })->map(function ($item, $key) {
                return ($item->sortByDesc('timestamp'))->values()->all();
            });
            return api_response($request, $dates, 200, ['logs' => $dates]);
        } catch (\Throwable $e) {
            return api_response($request, null, 500);
        }
    }

    public function getPayments($partner, Request $request)
    {
        try {
            $logs = $request->partner_order->payments->where('transaction_type', 'Debit');
            if (count($logs) != 0) {
                $logs->each(function ($item, $key) {
                    $name = explode('-', $item->created_by_name);
                    $item['collected_by'] = $item->created_by_name;
                    if (trim($name[0]) == 'Resource') {
                        $resource = Resource::find($item->created_by);
                        $item['picture_of_collected_by'] = $resource != null ? $resource->profile->pro_pic : null;
                    } elseif (trim($name[0]) == 'Customer') {
                        $item['picture_of_collected_by'] = 'https://s3.ap-south-1.amazonaws.com/cdn-shebaxyz/images/manager_app/customer.jpg';
                    } else {
                        $item['collected_by'] = 'Sheba.xyz';
                        $item['picture_of_collected_by'] = 'https://s3.ap-south-1.amazonaws.com/cdn-shebaxyz/images/manager_app/sheba.jpg';
                    }
                    removeSelectedFieldsFromModel($item);
                });
                return api_response($request, $logs, 200, ['logs' => $logs]);
            }
            return api_response($request, $logs, 404);
        } catch (\Throwable $e) {
            return api_response($request, null, 500);
        }
    }

    public function postComment($partner, Request $request)
    {
        try {
            $partner_order = $request->partner_order;
            $manager_resource = $request->manager_resource;
            $comment = (new CommentRepository('Job', $partner_order->jobs->pluck('id')->first(), $manager_resource))->store($request->comment, true);
            return $comment ? api_response($request, $comment, 200) : api_response($request, $comment, 500);
        } catch (\Throwable $e) {
            return api_response($request, null, 500);
        }
    }

    /**
     * @param $job_logs
     * @param $all_logs
     */
    private function formatLogs($job_logs, $all_logs)
    {
        foreach ($job_logs as $key => $job_log) {
            foreach ($job_log as $log) {
                $collect = collect($log);
                $collect->put('created_at', $log->created_at->toDateString());
                $collect->put('timestamp', $log->created_at->timestamp);
                $collect->put('type', $key);
                $all_logs->push($collect);
            }
        }
    }
}
