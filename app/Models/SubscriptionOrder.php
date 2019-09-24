<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Sheba\Checkout\SubscriptionOrderInterface;
use Sheba\Payment\PayableType;
use Sheba\Dal\SubscriptionOrderPayment\Model as SubscriptionOrderPayment;

class SubscriptionOrder extends Model implements SubscriptionOrderInterface, PayableType
{
    protected $dates = ['paid_at'];
    protected $guarded = ['id'];
    public $due;
    public $paid;
    public $totalPrice;

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }


    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function payments()
    {
        return $this->hasMany(SubscriptionOrderPayment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function schedules()
    {
        return json_decode($this->schedules);
    }

    public function deliveryAddress()
    {
        return $this->hasOne(CustomerDeliveryAddress::class, 'id', 'delivery_address_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function channelCode()
    {
        if (in_array($this->sales_channel, ['Web', 'Call-Center', 'App', 'Facebook', 'App-iOS', 'E-Shop'])) {
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
        return $this->channelCode() . '-' . sprintf('%06d', $this->id);
    }

    public function calculate()
    {
        $partner_orders = $this->orders->map(function ($order) {
            $partner_order = $order->lastPartnerOrder();
            $partner_order->calculate(1);
            return $partner_order;
        });
        $this->totalPrice = (double)$partner_orders->sum('totalPrice');
        $this->due = (double)$partner_orders->sum('due');
        $this->paid = (double)$partner_orders->sum('paid');
    }

    public function getTotalPrice()
    {
        if ($this->totalPrice == null) $this->calculate();
        return $this->totalPrice;
    }

    public function isPaid()
    {
        if ($this->due == null) $this->calculate();
        return $this->due > 0 ? 0 : 1;
    }


    /**
     * @return bool
     */
    public function hasOrders()
    {
        return $this->orders->count() > 0;
    }
}
