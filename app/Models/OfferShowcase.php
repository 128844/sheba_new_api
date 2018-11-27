<?php namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class OfferShowcase extends Model
{
    protected $guarded = ['id'];

    public function scopeActive($q)
    {
        return $q->where('is_active', 1);
    }

    public function scopeValid($q)
    {
        $now = Carbon::now();
        return $q->where('start_date', '<=', $now)->where('end_date', '>=', $now);
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'target_id');
    }

    public function target()
    {
        return $this->morphTo();
    }

    public function type()
    {
        return strtolower(snake_case(str_replace("App\\Models\\", '', $this->target_type)));
    }

    public function isVoucher()
    {
        return $this->type() == 'voucher' ? 1 : 0;
    }
}
