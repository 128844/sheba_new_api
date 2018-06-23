<?php

namespace App\Sheba\Checkout;

use App\Models\Partner;
use App\Models\PartnerService;
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
        $this->selected_services = $this->getSelectedServices($services);
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
        $this->partners = $this->findPartnersByServiceAndLocation((int)$partner_id);
        $this->partners->load(['services' => function ($q) {
            $q->whereIn('service_id', $this->selected_services->pluck('id')->unique());
        }, 'categories' => function ($q) {
            $q->where('categories.id', $this->selected_services->pluck('category_id')->unique()->first());
        }]);
        $selected_option_services = $this->selected_services->where('variable_type', 'Options');
        $this->filterByOption($selected_option_services);
        $this->filterByCreditLimit();
        $this->addAvailability();
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
        })->with(['resources' => function ($q) {
            $q->with('profile');
        }])->published()->select('partners.id', 'partners.name', 'partners.sub_domain', 'partners.description', 'partners.logo', 'partners.wallet');
        if ($partner_id != null) {
            $query = $query->where('partners.id', $partner_id);
        }
        return $query->get()->map(function ($partner) {
            $partner->contact_no = $partner->getContactNumber();
            return $partner;
        });
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
        $this->partners->load('walletSetting');
        $this->partners = $this->partners->filter(function ($partner, $key) {
            return ((new PartnerRepository($partner)))->hasAppropriateCreditLimit();
        });
    }

    private function addAvailability()
    {
        $this->partners->load(['workingHours', 'leaves']);
        $category_id = $this->selected_services->first()->category_id;
        $this->partners->each(function ($partner) use ($category_id) {
            $partner['is_available'] = $this->isWithinPreparationTime($partner, $category_id) && (new PartnerAvailable($partner))->available($this->date, $this->time, $category_id) ? 1 : 0;
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
            $pricing = $this->calculateServicePricingAndBreakdowntoPartner($partner);
            foreach ($pricing as $key => $value) {
                $partner[$key] = $value;
            }
        }
    }

    public function addInfo()
    {
        $this->partners->load(['jobs' => function ($q) {
            $q->validStatus();
        }]);
        foreach ($this->partners as $partner) {
            $partner['total_jobs'] = $partner->jobs->count();
            $partner['total_jobs_of_category'] = $partner->jobs->where('category_id', $this->selected_services->pluck('category_id')->unique()->first())->count();
        }
    }

    public function calculateAverageRating()
    {
        $this->partners->load('reviews');
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

    public function calculateOngoingJobs()
    {
        foreach ($this->partners as $partner) {
            $partner['ongoing_jobs'] = $partner->onGoingJobs();
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

    private function calculateServicePricingAndBreakdowntoPartner($partner)
    {
        $total_service_price = [
            'discount' => 0,
            'discounted_price' => 0,
            'original_price' => 0
        ];
        $services = [];
        foreach ($this->selected_services as $selected_service) {
            $service = $partner->services->where('id', $selected_service->id)->first();
            if ($service->isOptions()) {
                $price = $this->partnerServiceRepository->getPriceOfOptionsService($service->pivot->prices, $selected_service->option);
            } else {
                $price = (double)$service->pivot->prices;
            }
            $discount = $this->calculateDiscountForService($price, $selected_service, $service);
            $service = [];
            $service['discount'] = $discount->__get('discount');
            $service['cap'] = $discount->__get('cap');
            $service['amount'] = $discount->__get('amount');
            $service['is_percentage'] = $discount->__get('discount_percentage');
            $service['discounted_price'] = $discount->__get('discounted_price');
            $service['original_price'] = $discount->__get('original_price');
            $service['unit_price'] = $discount->__get('unit_price');
            $service['sheba_contribution'] = $discount->__get('sheba_contribution');
            $service['partner_contribution'] = $discount->__get('partner_contribution');

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

    private function calculateDiscountForService($price, $selected_service, $service)
    {
        $discount = new Discount($price, $selected_service->quantity);
        $discount->calculateServiceDiscount((PartnerService::find($service->pivot->id))->discount());
        return $discount;
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