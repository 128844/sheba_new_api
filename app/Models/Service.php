<?php namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Sheba\Dal\ServiceDiscount\Model as ServiceDiscount;

class Service extends Model
{
    protected $casts = ['min_quantity' => 'double'];
    protected $fillable = [
        'category_id',
        'name',
        'bn_name',
        'description',
        'publication_status',
        'recurring_possibility',
        'thumb',
        'banner',
        'faqs',
        'variable_type',
        'variables',
        'created_by',
        'created_by_name',
        'updated_by',
        'updated_by_name',
        'created_at',
        'updated_at'
    ];

    protected $servicePivotColumns = ['id', 'description', 'options', 'prices', 'is_published', 'discount', 'discount_start_date', 'discount_start_date', 'is_verified', 'verification_note', 'created_by', 'created_by_name', 'created_at', 'updated_by', 'updated_by_name', 'updated_at'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subCategory()
    {
        return $this->category();
    }

    public function getParentCategoryAttribute()
    {
        return $this->category->parent->id;
    }

    public function partners()
    {
        return $this->belongsToMany(Partner::class)->withPivot($this->servicePivotColumns);
    }

    public function subscription()
    {
        return $this->hasOne(ServiceSubscription::class);
    }

    public function groups()
    {
        return $this->belongsToMany(ServiceGroup::class, 'service_group_service');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function jobs()
    {
        return $this->hasMany(Job::class);
    }

    public function commission($partner_id)
    {
        $service_category = $this->category->id;
        $partner = Partner::find($partner_id);
        return $partner->categories()->find($service_category)->pivot->commission;
    }

    public function custom_services()
    {
        return $this->hasMany(CustomOrder::class);
    }

    public function partnerServices()
    {
        return $this->hasMany(PartnerService::class);
    }

    public function serviceDiscounts()
    {
        return $this->hasMany(ServiceDiscount::class);
    }

    public function runningDiscounts()
    {
        $running_discounts = [];
        foreach ($this->partnerServices as $partner_service) {
            if ($discount = $partner_service->discount()) {
                $running_discounts[] = $partner_service->discount();
            }
        }
        return collect($running_discounts);
    }

    public function runningDiscountOf($partner)
    {
        return $this->partnerServices()->where('partner_id', $partner)->first()->discount();
    }

    public function hasDiscounts()
    {
        $this->load(['partnerServices' => function ($q) {
            $q->published()->with(['partner' => function ($q) {
                $q->published();
            }])->with(['discounts' => function ($q) {
                $q->where([
                    ['start_date', '<=', Carbon::now()],
                    ['end_date', '>=', Carbon::now()]
                ]);
            }]);
        }]);
        foreach ($this->partnerServices as $partnerService) {
            if (count($partnerService->discounts) != 0) {
                return true;
            }
        }
        return false;
//        foreach ($this->partnerServices as $partner_service) {
//            if ($partner_service->is_verified == 1 && $partner_service->is_published == 1 && $partner_service->partner->status == 'Verified' && $partner_service->discount()) {
//                return true;
//            }
//        }
//        return false;
    }

    public function discounts()
    {
        return $this->load(['partnerServices' => function ($q) {
            $q->published()->with(['partner' => function ($q) {
                $q->published();
            }])->with(['discounts' => function ($q) {
                $q->where([
                    ['start_date', '<=', Carbon::now()],
                    ['end_date', '>=', Carbon::now()]
                ])->first();
            }]);
        }]);
    }

    /** Scope a query to only include published Service.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublished($query)
    {
        return $query->where('publication_status', 1);
    }

    /**
     * Scope a query to only include unpublished Service.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnpublished($query)
    {
        return $query->where('publication_status', 0);
    }

    /**
     * Scope a query to only include published and backend published service.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublishedForAll($query)
    {
        return $query->where('publication_status', 1)->orWhere(function ($query) {
            $query->publishedForBackendOnly();
        })->orWhere(function ($query) {
            $query->publishedForBusiness();
        })->orWhere(function ($query) {
            $query->publishedForBondhu();
        })->orWhere(function ($query) {
            $query->publishedForB2B();
        });
    }

    /**
     * Scope a query to only include backend published service.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublishedForBackendOnly($query)
    {
        return $query->where('publication_status', 0)->where('is_published_for_backend', 1);
    }

    public function scopePublishedForBusiness($query)
    {
        return $query->where('is_published_for_business', 1);
    }

    public function scopePublishedForB2B($query)
    {
        return $query->where('is_published_for_b2b', 1);
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function serviceSubscription()
    {
        return $this->hasOne(ServiceSubscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(ServiceSubscription::class)->active();
    }

    public function isOptions()
    {
        return $this->variable_type == 'Options';
    }

    public function isFixed()
    {
        return $this->variable_type == 'Fixed';
    }

    public function getVariablesOfOptionsService(array $options)
    {
        $variables = [];
        foreach ((array)(json_decode($this->variables))->options as $key => $service_option) {
            array_push($variables, [
                'question' => $service_option->question,
                'answer' => explode(',', $service_option->answers)[$options[$key]]
            ]);
        }
        return json_encode($variables);
    }

    public function variable()
    {
        return json_decode($this->variables);
    }

    public function flashPrice()
    {
        $variable = $this->variable();
        $defaultDiscount = (new \stdClass());
        $defaultDiscount->value = 0;
        $defaultDiscount->is_percentage = 0;
        return [
            'price' => isset($variable->price) ? (double)$variable->price : 0,
            'discounted_price' => isset($variable->discounted_price) ? (double)$variable->discounted_price : 0,
            'discount' => isset($variable->discount) ? $variable->discount : $defaultDiscount,
        ];
    }

    public function favorites()
    {
        return $this->belongsToMany(CustomerFavorite::class, 'customer_favourite_service', 'service_id', 'customer_favourite_id')->withPivot(['name', 'additional_info', 'variable_type', 'variables', 'option', 'quantity']);
    }

    public function scopePublishedForBondhu($query)
    {
        return $query->where('is_published_for_bondhu', 1);
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class);
    }
}
