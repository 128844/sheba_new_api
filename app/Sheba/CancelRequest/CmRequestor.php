<?php namespace Sheba\CancelRequest;

use App\Models\Department;
use App\Models\User;
use Auth;
use Exception;

class CmRequestor extends Requestor
{
    /**
     * @throws Exception
     */
    public function request()
    {
        $this->saveToDB();
        $this->freeResource();
        $this->notify();
    }

    /**
     * @throws Exception
     */
    protected function notify()
    {
        $order = $this->job->partnerOrder->order;
        notify()->department(Department::where('name', 'QC')->first())->send([
            "title" => Auth::user()->name . " requested to cancel a job: " . $order->code(),
            "link" => url("order/" . $order->id),
            "type" => notificationType('Danger')
        ]);
    }

    protected function getUserType()
    {
        return get_class(new User());
    }
}