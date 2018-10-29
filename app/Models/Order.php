<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Sheba\Order\StatusCalculator;

class Order extends Model
{
    protected $guarded = ['id'];
    public $totalPrice;
    public $due;
    public $profit;

    public function jobs()
    {
        return $this->hasManyThrough(Job::class, PartnerOrder::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function partner_orders()
    {
        return $this->hasMany(PartnerOrder::class);
    }

    public function partnerOrders()
    {
        return $this->hasMany(PartnerOrder::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function calculate($price_only = false)
    {
        $this->totalPrice = 0;
        $this->due = 0;
        foreach ($this->partner_orders as $partnerOrder) {
            $partnerOrder->calculate($price_only);
            $this->totalPrice += $partnerOrder->grossAmount;
            $this->due += $partnerOrder->due;
            $this->profit += $partnerOrder->profit;
        }
        $this->status = $this->getStatus();
        return $this;
    }

    public function getStatus()
    {
        return $this->isStatusCalculated() ? $this->status : (new StatusCalculator($this))->calculate();
    }

    private function isStatusCalculated()
    {
        return property_exists($this, 'status') && $this->status;
    }

    public function channelCode()
    {
        if (in_array($this->sales_channel, ['Web', 'Call-Center', 'App', 'Facebook', 'App-iOS'])) {
            $prefix = 'D';
        } elseif ($this->sales_channel == 'B2B') {
            $prefix = 'F';
        } elseif ($this->sales_channel == 'Store') {
            $prefix = 'S';
        } else {
            $prefix = 'A';
        }
        return $prefix;
    }

    public function code()
    {
        $startFrom = 8000;
        return $this->channelCode() . '-' . sprintf('%06d', $this->id + $startFrom);
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function updateLogs()
    {
        return $this->hasMany(OrderUpdateLog::class);
    }

    public function getVersion()
    {
        return $this->id > (int)env('LAST_ORDER_ID_V1') ? 'v2' : 'v1';
    }

    public function department()
    {
        return getSalesChannels('department')[$this->sales_channel];
    }

    public function isCancelled()
    {
        return $this->getStatus() == $this->statuses['Cancelled'];
    }

    public function lastJob()
    {
        if ($this->isCancelled()) return $this->jobs->last();
        return $this->jobs->filter(function ($job) {
            return $job->status != $this->jobStatuses['Cancelled'];
        })->first();
    }

}
