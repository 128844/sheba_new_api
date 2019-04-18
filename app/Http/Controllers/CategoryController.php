<?php namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\CategoryGroupCategory;
use App\Models\CategoryPartner;
use App\Models\HyperLocal;
use App\Models\Location;
use App\Models\Partner;
use App\Models\Service;
use App\Models\ServiceGroupService;
use App\Repositories\CategoryRepository;
use App\Repositories\ServiceRepository;
use Dingo\Api\Routing\Helpers;
use Illuminate\Http\Request;
use DB;
use Illuminate\Validation\ValidationException;
use Sheba\CategoryServiceGroup;
use Sheba\Location\Coords;
use Sheba\ModificationFields;

class CategoryController extends Controller
{
    use Helpers, ModificationFields, CategoryServiceGroup;
    private $categoryRepository;
    private $serviceRepository;

    public function __construct()
    {
        $this->categoryRepository = new CategoryRepository();
        $this->serviceRepository = new ServiceRepository();
    }

    public function index(Request $request)
    {
        $filter_publication = function ($q) use ($request) {
            $is_business = $request->has('is_business') && (int)$request->is_business;
            $is_partner = ($request->has('is_partner') && (int)$request->is_partner)
                || in_array($request->header('portal-name'), ['manager-app', 'bondhu-app']);

            if ($is_business) {
                $q->publishedForBusiness();
            } else if ($is_partner) {
                $q->publishedForPartner();
            } else {
                $q->published();
            }
        };

        try {
            $this->validate($request, ['location' => 'sometimes|numeric', 'lat' => 'sometimes|numeric', 'lng' => 'required_with:lat']);

            $with = '';
            $location = null;
            if ($request->has('location')) {
                $location = Location::find($request->location);
            } else if ($request->has('lat')) {
                $hyperLocation = HyperLocal::insidePolygon((double)$request->lat, (double)$request->lng)->with('location')->first();
                if (!is_null($hyperLocation)) $location = $hyperLocation->location;
            }
            $categories = Category::where('parent_id', null)->orderBy('order');
            if ($location) {
                $categories = $categories->whereHas('locations', function ($q) use ($location) {
                    $q->where('locations.id', $location->id);
                });
                $categories = $categories->whereHas('allChildren', function ($q) use ($location, $request, $filter_publication) {
                    $filter_publication($q);
                    $q->whereHas('locations', function ($query) use ($location) {
                        $query->where('locations.id', $location->id);
                    });
                });
            }
            $categories = $categories->select('id', 'name', 'bn_name', 'slug', 'thumb', 'banner', 'icon_png', 'icon', 'order', 'parent_id');

            if ($request->has('with')) {
                $with = $request->with;
                if ($with == 'children') {
                    $categories->with(['allChildren' => function ($q) use ($location, $filter_publication) {
                        if (!is_null($location)) {
                            $q->whereHas('locations', function ($q) use ($location) {
                                $q->where('locations.id', $location->id);
                            });
                            $q->whereHas('services', function ($q) use ($location) {
                                $q->published()->whereHas('locations', function ($q) use ($location) {
                                    $q->where('locations.id', $location->id);
                                });
                            });
                        }
                        $filter_publication($q);
                        $q->orderBy('order');
                    }]);
                }
            }

            $filter_publication($categories);
            //$categories = $request->has('is_business') && (int)$request->is_business ? $categories->publishedForBusiness() : $categories->published();
            $categories = $categories->get();

            foreach ($categories as $key => &$category) {
                if ($with == 'children') {
                    $category->children = $category->allChildren;
                    unset($category->allChildren);
                    if ($category->children->isEmpty()) {
                        $categories->forget($key);
                        continue;
                    }
                    $category->children->sortBy('order')->each(function (&$child) {
                        removeRelationsAndFields($child);
                    });
                }
            }

            $categories_final = array();
            foreach ($categories as $category) {
                array_push($categories_final, $category);
            }

            return count($categories) > 0 ? api_response($request, $categories, 200, ['categories' => $categories_final]) : api_response($request, null, 404);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function show($category, Request $request)
    {
        try {
            $category = Category::select('id', 'name', 'short_description', 'long_description', 'thumb', 'video_link', 'banner', 'app_thumb', 'app_banner', 'publication_status', 'icon', 'questions')->published()->where('id', $category)->first();
            if ($category == null) {
                return api_response($request, null, 404);
            }
            $category->load(['partners' => function ($q) {
                $q->verified();
            }, 'services' => function ($q) {
                $q->published();
            }, 'usps' => function ($q) {
                $q->select('usps.id', 'name', 'category_usp.value');
            }, 'partnerResources' => function ($q) {
                $q->whereHas('resource', function ($query) {
                    $query->verified();
                });
            }]);
            array_add($category, 'total_partners', $category->partners->count());
            array_add($category, 'total_experts', $category->partnerResources->count());
            array_add($category, 'total_services', $category->services->count());
            array_add($category, 'selling_points', $category->usps->each(function ($usp) {
                removeRelationsAndFields($usp);
            }));
            removeRelationsAndFields($category);
            return api_response($request, $category, 200, ['category' => $category]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getSecondaries($category, Request $request)
    {
        try {
            $this->validate($request, ['location' => 'sometimes|numeric', 'lat' => 'sometimes|numeric', 'lng' => 'required_with:lat']);
            $location = null;
            $category = Category::find($category);
            if ($request->has('location')) {
                $location = Location::find($request->location);
            } else if ($request->has('lat')) {
                $hyperLocation = HyperLocal::insidePolygon((double)$request->lat, (double)$request->lng)->with('location')->first();
                if (!is_null($hyperLocation)) $location = $hyperLocation->location;
            }

            $best_deal_categories_id = explode(',', config('sheba.best_deal_ids'));
            $best_deal_category = CategoryGroupCategory::whereIn('category_group_id', $best_deal_categories_id)->pluck('category_id')->toArray();

            if ($location) {
                $children = $category->load(['children' => function ($q) use ($best_deal_category, $location) {
                    $q->whereNotIn('id', $best_deal_category)
                        ->whereHas('locations', function ($q) use ($location) {
                            $q->where('locations.id', $location->id);
                        });
                    $q->whereHas('services', function ($q) use ($location) {
                        $q->published()->whereHas('locations', function ($q) use ($location) {
                            $q->where('locations.id', $location->id);
                        });
                    });
                }])->children;
            } else {
                $children = $category->children->filter(function ($sub_category) use ($best_deal_category) {
                    return !in_array($sub_category->id, $best_deal_category);
                });
            }

            if (count($children) != 0) {
                $children = $children->each(function (&$child) use ($location) {
                    removeRelationsAndFields($child);
                });
                $category = collect($category)->only(['name', 'banner', 'app_banner']);
                $category->put('secondaries', $children->sortBy('order')->values()->all());
                return api_response($request, $category->all(), 200, ['category' => $category->all()]);
            } else
                return api_response($request, null, 404);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getMaster($category)
    {
        $category = Category::find($category);
        $parent = $category->parent()->select('id', 'name', 'thumb', 'banner')->first();
        if ($parent) return response()->json(['parent' => $parent, 'msg' => 'successful', 'code' => 200]);
        return response()->json(['msg' => 'not found', 'code' => 404]);
    }

    public function getPartnersOfLocation($category, $location, Request $request)
    {
        try {
            $category = Category::find($category);
            $category->load(['partners' => function ($q) use ($location) {
                $q->verified()->whereHas('locations', function ($q) use ($location) {
                    $q->where('locations.id', (int)$location);
                });
            }]);
            $available_partners = $category->partners;
            $total_available_partners = count($available_partners);
            return api_response($request, $available_partners, 200, ['total_available_partners' => $total_available_partners, 'isAvailable' => $total_available_partners > 0 ? 1 : 0]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getServices($category, Request $request)
    {
        ini_set('memory_limit', '2048M');
        try {
            if ($request->has('location')) {
                $location = $request->location != '' ? $request->location : 4;
            } else {
                if ($request->has('lat') && $request->has('lng')) {
                    $hyperLocation = HyperLocal::insidePolygon((double)$request->lat, (double)$request->lng)->with('location')->first();
                    if (!is_null($hyperLocation)) $location = $hyperLocation->location->id; else return api_response($request, null, 404);
                } else $location = 4;
            }

            $category = Category::where('id', $category)->whereHas('locations', function ($q) use ($location) {
                $q->where('locations.id', $location);
            });

            $category = ((int)$request->is_business ? $category->publishedForBusiness() : $category->published())->first();
            if ($category != null) {
                list($offset, $limit) = calculatePagination($request);
                $scope = [];
                if ($request->has('scope')) $scope = $this->serviceRepository->getServiceScope($request->scope);
                if ($category->parent_id == null) {
                    if ((int)$request->is_business) {
                        $services = $this->categoryRepository->getServicesOfCategory((Category::where('parent_id', $category->id)->publishedForBusiness()->orderBy('order')->get())->pluck('id')->toArray(), $location, $offset, $limit);
                    } else {
                        $services = $this->categoryRepository->getServicesOfCategory($category->children->sortBy('order')->pluck('id'), $location, $offset, $limit);
                    }
                    $services = $this->serviceRepository->addServiceInfo($services, $scope);
                } else {
                    $category = $category->load(['services' => function ($q) use ($offset, $limit, $location) {
                        $q->whereNotIn('id', [74])
                            ->whereHas('locations', function ($query) use ($location) {
                                $query->where('locations.id', $location);
                            });
                        $q->select('id', 'category_id', 'unit', 'name', 'bn_name', 'thumb', 'app_thumb', 'app_banner', 'short_description', 'description', 'banner', 'faqs', 'variables', 'variable_type', 'min_quantity')->orderBy('order')->skip($offset)->take($limit);
                        if ((int)\request()->is_business) $q->publishedForBusiness();
                        elseif ((int)\request()->is_for_backend) $q->publishedForAll();
                        else $q->published();
                    }]);

                    $services = $this->serviceRepository->getPartnerServicesAndPartners($category->services, $location)->each(function ($service) use ($request) {
                        $service->partners = $service->partners->filter(function (Partner $partner) use ($request) {
                            return $partner->hasCoverageOn(new Coords((double)$request->lat, (double)$request->lng));
                        });
                        list($service['max_price'], $service['min_price']) = $this->getPriceRange($service);
                        removeRelationsAndFields($service);
                    });
                }

                if ($location) {
                    $services = collect($services);
                    $services = $services->filter(function ($service) use ($location) {
                        $locations = $service->locations->pluck('id')->toArray();
                        removeRelationsAndFields($service);
                        return in_array($location, $locations);
                    });
                }

                $subscriptions = collect();
                foreach ($services as $service) {
                    if ($service->serviceSubscription) {
                        $subscription = $service->serviceSubscription;
                        list($service['max_price'], $service['min_price']) = $this->getPriceRange($service);
                        $subscription->min_price = $service->min_price;
                        $subscription->max_price = $service->max_price;
                        $subscription['thumb'] = $service['thumb'];
                        $subscription['banner'] = $service['banner'];
                        $subscription['offers'] = $subscription->getDiscountOffers();
                        removeRelationsAndFields($service);
                        $subscriptions->push($subscription);
                    }
                }

                if ($services->count() > 0) {
                    $category = collect($category)->only(['name', 'slug', 'banner', 'parent_id', 'app_banner']);
                    $version_code = (int)$request->header('Version-Code');
                    $services = $this->serviceQuestionSet($services);
                    if ($version_code && $version_code <= 30122 && $version_code <= 107) {
                        $services = $services->reject(function ($service) use ($version_code) {
                            return $service->subscription;
                        })->values()->all();
                    }
                    $category['services'] = $services;
                    $category['subscriptions'] = $subscriptions;
                    if ($subscriptions->count()) {
                        $category['subscription_faq'] = [
                            'title' => 'Subscribe & save money',
                            'body' => 'Save BDT 20 in every meter by subscribing for one month!'
                        ];
                    }
                    return api_response($request, $category, 200, ['category' => $category]);
                } else
                    return api_response($request, null, 404);
            } else {
                return api_response($request, null, 404);
            }
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    private function getPriceRange(Service $service)
    {
        try {
            $max_price = [];
            $min_price = [];
            if ($service->partners->count() == 0) return array(0, 0);
            foreach ($service->partners->where('status', 'Verified') as $partner) {
                $partner_service = $partner->pivot;
                if (!($partner_service->is_verified && $partner_service->is_published)) continue;
                $prices = (array)json_decode($partner_service->prices);
                $max = max($prices);
                $min = min($prices);
                array_push($max_price, $max);
                array_push($min_price, $min);
            }
            return array((double)max($max_price) * $service->min_quantity, (double)min($min_price) * $service->min_quantity);
        } catch (\Throwable $e) {
            return array(0, 0);
        }
    }

    private function serviceQuestionSet($services)
    {
        foreach ($services as &$service) {
            $questions = null;
            $service['type'] = 'normal';
            if ($service->variable_type == 'Options') {
                $questions = json_decode($service->variables)->options;
                foreach ($questions as &$question) {
                    $question = collect($question);
                    $question->put('input_type', $this->resolveInputTypeField($question->get('answers')));
                    $question->put('screen', count($questions) > 3 ? 'slide' : 'normal');
                    $explode_answers = explode(',', $question->get('answers'));
                    $question->put('answers', $explode_answers);
                }
                if (count($questions) == 1) {
                    $questions[0]->put('input_type', 'selectbox');
                }
            }
            $service['questions'] = $questions;
            $service['faqs'] = json_decode($service->faqs);
            array_forget($service, 'variables');
        }
        return $services;
    }

    private function resolveInputTypeField($answers)
    {
        $answers = explode(',', $answers);
        return count($answers) <= 4 ? "radiobox" : "dropdown";
    }

    private function resolveScreenField($question)
    {
        $words = explode(' ', trim($question));
        return count($words) <= 5 ? "normal" : "slide";
    }

    public function getReviews($category, Request $request)
    {
        try {
            list($offset, $limit) = calculatePagination($request);
            $category = Category::find($category);
            if (!$category) return api_response($request, null, 404);
            $category->load(['reviews' => function ($q) use ($offset, $limit) {
                $q->select('id', 'category_id', 'customer_id', 'rating', 'review', 'review_title', 'partner_id')->whereIn('rating', [4, 5])->orderBy('created_at', 'desc')->with(['rates', 'customer.profile', 'partner']);
            }]);
            $reviews = $category->reviews->each(function ($review) {
                $review->review = $review->calculated_review;
                $review['customer_name'] = $review->customer ? $review->customer->profile->name : null;
                $review['customer_picture'] = $review->customer ? $review->customer->profile->pro_pic : null;
                $review['partner_name'] = $review->partner->name;
                removeRelationsAndFields($review);
            })->filter(function ($review) {
                return (!empty($review->review) && $review->rating == 5);
            })->unique('customer_id')->sortByDesc('id')->splice($offset, $limit)->values()->all();
            return count($reviews) > 0 ? api_response($request, $reviews, 200, ['reviews' => $reviews]) : api_response($request, null, 404);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function addCategories(Request $request)
    {
        try {
            $this->validate($request, ['categories' => "required|string"]);
            $partner = $request->partner;
            $manager_resource = $request->manager_resource;
            $this->setModifier($manager_resource);
            $categories = explode(',', $request->categories);
            $partner_categories = CategoryPartner::where('partner_id', $partner->id)->whereIn('category_id', $categories)->get();
            $category_partners = [];
            foreach ($categories as $category) {
                $has_category_partner = $partner_categories->where('category_id', (int)$category)->first();
                if (!$has_category_partner) {
                    array_push($category_partners, $this->withCreateModificationField([
                        'response_time_min' => 60,
                        'response_time_max' => 120,
                        'commission' => $partner->commission,
                        'category_id' => $category,
                        'partner_id' => $partner->id
                    ]));
                }
            }
            CategoryPartner::insert($category_partners);
            return api_response($request, null, 200);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getPartnerLocationCategory(Request $request, $partner)
    {
        try {
            $geo_info = json_decode($request->partner->geo_informations);
            $hyper_locations = HyperLocal::insideCircle($geo_info)->with('location')->get()->filter(function ($item) {
                return !empty($item->location);
            })->pluck('location')->pluck('id');
            $category = Category::locationWise($hyper_locations)->get();
            $category = $category->filter(function ($item) {
                return $item->children->count() > 0;
            });
            if ($category->count() > 0) {
                return api_response($request, $request, 200, ['data' => ['categories' => $category]]);
            } else {
                return api_response($request, null, 404);
            }
        } catch (\Throwable $e) {
            return api_response($request, null, 500, ['message' => $e->getMessage()]);
        }
    }
}
