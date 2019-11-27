<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Sheba\Dal\ServiceDiscount\Model as ServiceDiscount;

class LocationService extends Model
{
    public $timestamps = false;
    protected $table = 'location_service';

    public function discounts()
    {
        return $this->belongsToMany(ServiceDiscount::class, 'location_service_service_discount', 'location_service_id', 'service_discount_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
