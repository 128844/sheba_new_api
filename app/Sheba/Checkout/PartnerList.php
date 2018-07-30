<?php

namespace App\Sheba\Checkout;

use App\Models\Category;
use App\Models\Partner;
use App\Models\PartnerServiceDiscount;
use App\Models\Service;
use App\Repositories\PartnerRepository;
use App\Repositories\PartnerServiceRepository;
use App\Repositories\ReviewRepository;
use App\Sheba\Partner\PartnerAvailable;
use Carbon\Carbon;
use DB;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class PartnerList
{
    public $partners;
    public $hasPartners = false;
    public $selected_services;
    private $location;
    private $date;
    private $time;
    private $partnerServiceRepository;
    private $rentCarServicesId;

    public function __construct($services, $date, $time, $location)
    {
        $this->location = (int)$location;
        $this->date = $date;
        $this->time = $time;
        $this->rentCarServicesId = array_map('intval', explode(',', env('RENT_CAR_SERVICE_IDS')));
        $start = microtime(true);
        $this->selected_services = $this->getSelectedServices($services);
        $this->selectedCategory = Category::find($this->selected_services->first()->category_id);
        $time_elapsed_secs = microtime(true) - $start;
        dump("add selected service info: " . $time_elapsed_secs * 1000);
        $this->partnerServiceRepository = new PartnerServiceRepository();
    }

    private function getSelectedServices($services)
    {
        $selected_services = collect();
        foreach ($services as $service) {
            $selected_service = Service::where('id', $service->id)->publishedForAll()->first();
            $selected_service['option'] = $service->option;
            $selected_service['pick_up_location_id'] = isset($service->pick_up_location_id) ? $service->pick_up_location_id : null;
            $selected_service['pick_up_location_type'] = isset($service->pick_up_location_type) ? $service->pick_up_location_type : null;
            $selected_service['pick_up_address'] = isset($service->pick_up_address) ? $service->pick_up_address : null;
            if ($selected_service->category_id != (int)env('RENT_CAR_OUTSIDE_ID')) {
                $selected_service['destination_location_id'] = null;
                $selected_service['destination_location_type'] = null;
                $selected_service['destination_address'] = null;
                $selected_service['drop_off_date'] = null;
                $selected_service['drop_off_time'] = null;
            } else {
                $selected_service['destination_location_id'] = isset($service->destination_location_id) ? $service->destination_location_id : null;
                $selected_service['destination_location_type'] = isset($service->destination_location_type) ? $service->destination_location_type : null;
                $selected_service['destination_address'] = isset($service->destination_address) ? $service->destination_address : null;
                $selected_service['drop_off_date'] = isset($service->drop_off_date) ? $service->drop_off_date : null;
                $selected_service['drop_off_time'] = isset($service->drop_off_time) ? $service->drop_off_time : null;
            }
            if (in_array($selected_service->id, $this->rentCarServicesId)) {
                $model = "App\\Models\\" . $service->pick_up_location_type;
                $origin = $model::find($service->pick_up_location_id);
                $selected_service['pick_up_address_geo'] = json_encode(array('lat' => $origin->lat, 'lng' => $origin->lng));
                $model = "App\\Models\\" . $service->destination_location_type;
                $destination = $model::find($service->destination_location_id);
                $selected_service['destination_address_geo'] = json_encode(array('lat' => $destination->lat, 'lng' => $destination->lng));
                $data = $this->getDistanceCalculationResult($origin->lat . ',' . $origin->lng, $destination->lat . ',' . $destination->lng);
                $selected_service['quantity'] = (double)($data->rows[0]->elements[0]->distance->value) / 1000;
                $selected_service['estimated_distance'] = $selected_service['quantity'];
                $selected_service['estimated_time'] = (double)($data->rows[0]->elements[0]->duration->value) / 60;
            } else {
                $selected_service['quantity'] = $this->getSelectedServiceQuantity($service, (double)$selected_service->min_quantity);
            }
            $selected_services->push($selected_service);
        }
        return $selected_services;
    }

    private function getSelectedServiceQuantity($service, $min_quantity)
    {
        if (isset($service->quantity)) {
            $quantity = (double)$service->quantity;
            return $quantity >= $min_quantity ? $quantity : $min_quantity;
        } else {
            return $min_quantity;
        }
    }


    private function getDistanceCalculationResult($origin, $destination)
    {
        $client = new Client();
        try {
            $res = $client->request('GET', 'https://maps.googleapis.com/maps/api/distancematrix/json',
                [
                    'query' => ['origins' => $origin, 'destinations' => $destination, 'key' => env('GOOGLE_DISTANCEMATRIX_KEY'), 'mode' => 'driving']
                ]);
            return json_decode($res->getBody());
        } catch (RequestException $e) {
            return null;
        }
    }

    public function find($partner_id = null)
    {
        $start = microtime(true);
        $this->partners = $this->findPartnersByServiceAndLocation((int)$partner_id);
        $time_elapsed_secs = microtime(true) - $start;
        dump("filter partner by service,location,category: " . $time_elapsed_secs * 1000);

        $start = microtime(true);
        $this->partners->load(['services' => function ($q) {
            $q->whereIn('service_id', $this->selected_services->pluck('id')->unique());
        }, 'categories' => function ($q) {
            $q->where('categories.id', $this->selected_services->pluck('category_id')->unique()->first());
        }]);
        $time_elapsed_secs = microtime(true) - $start;
        dump("load partner service and category: " . $time_elapsed_secs * 1000);

        $start = microtime(true);
        $selected_option_services = $this->selected_services->where('variable_type', 'Options');
        $this->filterByOption($selected_option_services);
        $time_elapsed_secs = microtime(true) - $start;
        dump("filter partner by option: " . $time_elapsed_secs * 1000);

        $start = microtime(true);
        $this->filterByCreditLimit();
        $time_elapsed_secs = microtime(true) - $start;
        dump("filter partner by credit: " . $time_elapsed_secs * 1000);

        $start = microtime(true);
        $this->addAvailability();
        $time_elapsed_secs = microtime(true) - $start;
        dump("filter partner by availability: " . $time_elapsed_secs * 1000);
        $this->calculateHasPartner();
    }

    private function findPartnersByServiceAndLocation($partner_id = null)
    {
        $service_ids = $this->selected_services->pluck('id')->unique();
        $category_ids = $this->selected_services->pluck('category_id')->unique()->toArray();
        $query = Partner::WhereHas('categories', function ($q) use ($category_ids) {
            $q->whereIn('categories.id', $category_ids)->where('category_partner.is_verified', 1);
        })->whereHas('locations', function ($query) {
            $query->where('locations.id', (int)$this->location);
        })->whereHas('services', function ($query) use ($service_ids) {
            $query->whereHas('category', function ($q) {
                $q->published();
            })->select(DB::raw('count(*) as c'))->whereIn('services.id', $service_ids)->where([['partner_service.is_published', 1], ['partner_service.is_verified', 1]])->publishedForAll()
                ->groupBy('partner_id')->havingRaw('c=' . count($service_ids));
        })->published()->select('partners.id', 'partners.name', 'partners.sub_domain', 'partners.description', 'partners.logo', 'partners.wallet', 'partners.package_id');
        if ($partner_id != null) {
            $query = $query->where('partners.id', $partner_id);
        }
        return $query->get();
    }

    private function getContactNumber($partner)
    {
        if ($operation_resource = $partner->resources->where('pivot.resource_type', constants('RESOURCE_TYPES')['Operation'])->first()) {
            return $operation_resource->profile->mobile;
        } elseif ($admin_resource = $partner->resources->where('pivot.resource_type', constants('RESOURCE_TYPES')['Admin'])->first()) {
            return $admin_resource->profile->mobile;
        }
        return null;
    }

    private function filterByOption($selected_option_services)
    {
        foreach ($selected_option_services as $selected_option_service) {
            $this->partners = $this->partners->filter(function ($partner, $key) use ($selected_option_service) {
                $service = $partner->services->where('id', $selected_option_service->id)->first();
                return $this->partnerServiceRepository->hasThisOption($service->pivot->prices, implode(',', $selected_option_service->option));
            });
        }
    }

    private function filterByCreditLimit()
    {
        $this->partners->load(['walletSetting' => function ($q) {
            $q->select('id', 'partner_id', 'min_wallet_threshold');
        }]);
        $this->partners = $this->partners->filter(function ($partner, $key) {
            return ((new PartnerRepository($partner)))->hasAppropriateCreditLimit();
        });
    }

    private function addAvailability()
    {
        $this->partners->load(['workingHours', 'leaves']);
        $category = $this->selected_services->first()->category;
        $this->partners->each(function ($partner) use ($category) {
            $partner['is_available'] = $this->isWithinPreparationTime($partner, $category->id) && (new PartnerAvailable($partner))->available($this->date, $this->time, $category) ? 1 : 0;
        });
        $available_partners = $this->partners->where('is_available', 1);
        if ($available_partners->count() > 1) {
            $this->rejectShebaHelpDesk();
        }
    }

    public function isWithinPreparationTime($partner, $category_id)
    {
        $category_preparation_time_minutes = $partner->categories->where('id', $category_id)->first()->pivot->preparation_time_minutes;
        if ($category_preparation_time_minutes == 0) return 1;
        $start_time = Carbon::parse($this->date . explode('-', $this->time)[0]);
        $end_time = Carbon::parse($this->date . explode('-', $this->time)[1]);
        $preparation_time = Carbon::createFromTime(Carbon::now()->hour)->addMinute(61)->addMinute($category_preparation_time_minutes);
        return $preparation_time->lte($start_time) || $preparation_time->between($start_time, $end_time) ? 1 : 0;
    }

    public function addPricing()
    {
        foreach ($this->partners as $partner) {
            $pricing = $this->calculateServicePricingAndBreakdownOfPartner($partner);
            foreach ($pricing as $key => $value) {
                $partner[$key] = $value;
            }
        }
    }

    public function addInfo()
    {
        $this->partners->load(['jobs' => function ($q) {
            $q->select('jobs.id', 'jobs.partner_order_id', 'status', 'category_id')->validStatus();
        }, 'subscription' => function ($q) {
            $q->select('id', 'name');
        }, 'resources' => function ($q) {
            $q->select('resources.id', 'profile_id')->with(['profile' => function ($q) {
                $q->select('profiles.id', 'mobile');
            }]);
        }]);
        foreach ($this->partners as $partner) {
            $partner['total_jobs'] = $partner->jobs->count();
            $partner['ongoing_jobs'] = $partner->jobs->whereIn('status', ['Accepted', 'Schedule Due', 'Process', 'Serve Due'])->count();
            $partner['total_jobs_of_category'] = $partner->jobs->where('category_id', $this->selected_services->pluck('category_id')->unique()->first())->count();
            $partner['contact_no'] = $this->getContactNumber($partner);
            $partner['subscription_type'] = $partner->subscription ? $partner->subscription->name : null;
        }
    }

    public function calculateAverageRating()
    {
        $this->partners->load(['reviews' => function ($q) {
            $q->select('reviews.id', 'rating', 'category_id', 'partner_id');
        }]);
        foreach ($this->partners as $partner) {
            $partner['rating'] = (new ReviewRepository())->getAvgRating($partner->reviews);
        }
    }

    public function calculateTotalRatings()
    {
        foreach ($this->partners as $partner) {
            $partner['total_ratings'] = count($partner->reviews);
            $partner['total_five_star_ratings'] = count($partner->reviews->filter(function ($review) {
                return $review->rating == 5;
            }));
        }
    }

    public function sortByShebaSelectedCriteria()
    {
        $this->sortByRatingDesc();
        $this->sortByLowestPrice();
        $this->sortByAvailability();
    }

    private function sortByAvailability()
    {
        $unavailable_partners = $this->partners->filter(function ($partner, $key) {
            return $partner->is_available == 0;
        });
        $available_partners = $this->partners->filter(function ($partner, $key) {
            return $partner->is_available == 1;
        });
        $this->partners = $available_partners->merge($unavailable_partners);
    }

    private function sortByRatingDesc()
    {
        $this->partners = $this->partners->sortByDesc(function ($partner, $key) {
            return $partner->rating;
        });
    }

    private function sortByLowestPrice()
    {
        $this->partners = $this->partners->sortBy(function ($partner, $key) {
            return $partner->discounted_price;
        });
    }

    private function calculateServicePricingAndBreakdownOfPartner($partner)
    {
        $total_service_price = [
            'discount' => 0,
            'discounted_price' => 0,
            'original_price' => 0,
            'is_min_price_applied' => 0,
        ];
        $services = [];
        foreach ($this->selected_services as $selected_service) {
            $service = $partner->services->where('id', $selected_service->id)->first();
            if ($service->isOptions()) {
                $price = $this->partnerServiceRepository->getPriceOfOptionsService($service->pivot->prices, $selected_service->option);
                $min_price = empty($service->pivot->min_prices) ? 0 : $this->partnerServiceRepository->getMinimumPriceOfOptionsService($service->pivot->min_prices, $selected_service->option);
            } else {
                $price = (double)$service->pivot->prices;
                $min_price = (double)$service->pivot->min_prices;
            }
            $discount = new Discount($price, $selected_service->quantity, $min_price);
            $discount->calculateServiceDiscount(PartnerServiceDiscount::where('partner_service_id', $service->pivot->id)->running()->first());
            $service = [];
            $service['discount'] = $discount->__get('discount');
            $service['cap'] = $discount->__get('cap');
            $service['amount'] = $discount->__get('amount');
            $service['is_percentage'] = $discount->__get('isDiscountPercentage');
            $service['discounted_price'] = $discount->__get('discounted_price');
            $service['original_price'] = $discount->__get('original_price');
            $service['min_price'] = $discount->__get('min_price');
            $service['unit_price'] = $discount->__get('unit_price');
            $service['sheba_contribution'] = $discount->__get('sheba_contribution');
            $service['partner_contribution'] = $discount->__get('partner_contribution');
            $service['is_min_price_applied'] = $discount->__get('original_price') == $discount->__get('min_price') ? 1 : 0;
            if ($discount->__get('original_price') == $discount->__get('min_price')) {
                $total_service_price['is_min_price_applied'] = 1;
            }
            $total_service_price['discount'] += $service['discount'];
            $total_service_price['discounted_price'] += $service['discounted_price'];
            $total_service_price['original_price'] += $service['original_price'];
            $service['id'] = $selected_service->id;
            $service['name'] = $selected_service->name;
            $service['option'] = $selected_service->option;
            $service['quantity'] = $selected_service->quantity;
            $service['unit'] = $selected_service->unit;
            list($option, $variables) = $this->getVariableOptionOfService($selected_service, $selected_service->option);
            $service['questions'] = json_decode($variables);
            array_push($services, $service);
        }
        array_add($partner, 'breakdown', $services);
        return $total_service_price;
    }

    private function calculateHasPartner()
    {
        if (count($this->partners) > 0) {
            $this->hasPartners = true;
        }
    }

    private function getVariableOptionOfService(Service $service, Array $option)
    {
        if ($service->variable_type == 'Options') {
            $variables = [];
            $options = implode(',', $option);
            foreach ((array)(json_decode($service->variables))->options as $key => $service_option) {
                array_push($variables, [
                    'question' => $service_option->question,
                    'answer' => explode(',', $service_option->answers)[$option[$key]]
                ]);
            }
            $option = '[' . $options . ']';
            $variables = json_encode($variables);
        } else {
            $option = '[]';
            $variables = '[]';
        }
        return array($option, $variables);
    }

    private function rejectShebaHelpDesk()
    {
        try {
            $this->partners = $this->partners->reject(function ($partner) {
                return $partner->id == 1809;
            });
        } catch (\Throwable $e) {

        }
    }

}