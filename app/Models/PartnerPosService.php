<?php namespace App\Models;

use AlgoliaSearch\Laravel\AlgoliaEloquentTrait;
use App\Sheba\Algolia\Events\PartnerPosServiceCreated;
use App\Sheba\Algolia\Events\PartnerPosServiceSaved;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sheba\Dal\BaseModel;


class PartnerPosService extends BaseModel
{
    use SoftDeletes, AlgoliaEloquentTrait;

    protected $guarded = ['id'];
    protected $casts   = ['cost' => 'double', 'price' => 'double', 'stock' => 'double', 'vat_percentage' => 'double', 'show_image' => 'int'];
    protected $dates   = ['deleted_at'];

    public static $savedEventClass = PartnerPosServiceSaved::class;
    public static $createdEventClass = PartnerPosServiceCreated::class;
    public static $autoIndex = false;

    public $algoliaSettings = [
        'searchableAttributes' => [
            'name',
            'description',
        ],
        'attributesForFaceting' => ['partner'],
        'unretrievableAttributes' => [
            'partner'
        ]
    ];

    public function subCategory()
    {
        return $this->category()->with('parent');
    }

    public function category()
    {
        return $this->belongsTo(PosCategory::class, 'pos_category_id');
    }

    public function scopePublished($query)
    {
        return $query->where('publication_status', 1);
    }

    /**
     * Scope a query to only include a specific master category.
     *
     * @param Builder $query
     * @param $master_category_id
     * @return Builder
     */
    public function scopeOfParentCategory($query, $master_category_id)
    {
        return $query->whereHas('category', function ($q) use ($master_category_id) {
            $q->where('parent_id', $master_category_id);
        });
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function scopePartner($query, $partner_id)
    {
        return $query->where('partner_id', $partner_id);
    }

    public function discounts()
    {
        return $this->hasMany(PartnerPosServiceDiscount::class);
    }

    public function getPriceAttribute($price)
    {
        return $price ?: 0.0;
    }

    public function getDiscountedAmount()
    {
        $amount = $this->price - $this->getDiscount();
        return ($amount < 0) ? 0 : (float)$amount;
    }

    public function getDiscount()
    {
        $discount = $this->discount();
        if ($discount->is_amount_percentage) {
            $amount = ($this->price * $discount->amount) / 100;
            if ($discount->hasCap()) {
                $amount = ($amount > $discount->cap) ? $discount->cap : $amount;
            }
        } else {
            $amount = $discount->amount;
        }

        return ($amount < 0) ? 0 : (float)$amount;
    }

    public function discount()
    {
        return $this->runningDiscounts()->first();
    }

    public function runningDiscounts()
    {
        $now = Carbon::now();
        /**
         * USING AS A QUERY, THAT INCREASING LOAD TIME ON LIST VIEW
         *
         * return $this->discounts()->where(function ($query) use ($now) {
         * $query->where('start_date', '<=', $now);
         * $query->where('end_date', '>=', $now);
         * })->get();*/
        return $this->discounts->filter(function ($discount) use ($now) {
            return $discount->start_date <= $now && $discount->end_date >= $now;
        });
    }

    public function logs()
    {
        return $this->hasMany(PartnerPosServiceLog::class);
    }

    public function getAlgoliaRecord()
    {
        return [
            'id' => $this->id,
            'partner_id' => $this->partner_id,
            'category_id' => $this->pos_category_id,
            'category_name' => $this->category->name,
            'name' => $this->name,
            'description' => $this->description,
            'publication_status' => $this->publication_status,
            'is_published_for_shop' => $this->is_published_for_shop,
            'app_thumb' => $this->app_thumb,
        ];
    }
}
