<?php namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $guarded = ['id'];
    protected $dates = ['start_date', 'end_date'];
    protected $casts = ['is_amount_percentage' => 'integer', 'cap' => 'double', 'amount' => 'double'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function movieTicketOrders()
    {
        return $this->hasMany(MovieTicketOrder::class);
    }

    public function usage(Profile $profile)
    {
        return $this->usageCalculator->setVoucher($this)->usage($profile);
    }

    public function usedCustomerCount()
    {
        return $this->usageCalculator->setVoucher($this)->usedCustomerCount();
    }

    public function hasNotReachedMaxCustomer()
    {
        return $this->usedCustomerCount() < $this->max_customer;
    }

    public function hasNotReachedMaxOrder(Profile $profile)
    {
        return $this->usage($profile) < $this->max_order;
    }

    public function hasReachedMaxOrder(Profile $profile)
    {
        return $this->usage($profile) >= $this->max_order;
    }

    public function promotions()
    {
        return $this->hasMany(Promotion::class);
    }

    public function owner()
    {
        return $this->morphTo();
    }

    public function validityTimeLine($customer_id)
    {
        if ($this->is_referral) {
            $promotion = $this->activatedPromo($customer_id);
            if (!$promotion)
                return [Carbon::today(), Carbon::tomorrow()];
            return [$promotion->created_at, $promotion->valid_till];
        }
        return [$this->start_date, $this->end_date];
    }

    private function activatedPromo($customer_id)
    {
        $customer = Customer::find($customer_id);
        if (!$customer) return false;
        $promotion = $customer->promotions()->where('voucher_id', $this->id)->get();
        return $promotion == null ? false : $promotion->first();
    }

    public function ownerIsCustomer()
    {
        return $this->owner_type == "App\\Models\\Customer";
    }

    public function ownerIsAffiliate()
    {
        return $this->owner_type == "App\\Models\\Affiliate";
    }

    /**
     * Scope a query to only include voucher.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySheba($query)
    {
        return $query->where('is_created_by_sheba', 1);
    }


    /**
     * Scope a query to only include voucher.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValid($query)
    {
        return $query->whereRaw('((NOW() BETWEEN start_date AND end_date) OR (NOW() >= start_date AND end_date IS NULL))');
    }
}
