<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Service;
use App\Repositories\ReviewRepository;
use App\Repositories\ServiceRepository;
use App\Sheba\JobTime;
use Carbon\Carbon;
use Dingo\Api\Routing\Helpers;
use Illuminate\Http\Request;
use DB;
use Sheba\Subscription\ApproximatePriceCalculator;

class ServiceController extends Controller
{
    use Helpers;
    private $serviceRepository;
    private $reviewRepository;

    public function __construct(ServiceRepository $srp, ReviewRepository $reviewRepository)
    {
        $this->serviceRepository = $srp;
        $this->reviewRepository = $reviewRepository;
    }

    public function index(Request $request)
    {
        try {
            list($offset, $limit) = calculatePagination($request);
            $services = Service::select('id', 'name', 'bn_name', 'unit', 'category_id', 'thumb', 'slug', 'min_quantity', 'banner', 'variable_type');
            $scope = ['start_price'];
            if ($request->has('is_business')) $services = $services->publishedForBusiness();
            $services = $services->skip($offset)->take($limit)->get();
            $services = $this->serviceRepository->getpartnerServicePartnerDiscount($services);
            $services = $this->serviceRepository->addServiceInfo($services, $scope);
            if ($request->has('is_business')) {
                $categories = $services->unique('category_id')->pluck('category_id')->toArray();
                $master_categories = Category::select('id', 'parent_id')->whereIn('id', $categories)->get()
                    ->pluck('parent_id', 'id')->toArray();
                $services->map(function ($service) use ($master_categories) {
                    $service['master_category_id'] = $master_categories[$service->category_id];
                });
            }
            return count($services) != 0 ? api_response($request, $services, 200, ['services' => $services]) : api_response($request, null, 404);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function get($service, Request $request, ApproximatePriceCalculator $approximatePriceCalculator)
    {
        try {
            $service = Service::where('id', $service)->select('id', 'name', 'unit', 'structured_description', 'category_id', 'short_description', 'description', 'thumb', 'slug', 'min_quantity', 'banner', 'faqs', 'bn_name', 'bn_faqs', 'variable_type', 'variables');


            #$offer = $service->first()->groups()->first() ? $service->first()->groups()->first()->offers()->where('end_date', '>', Carbon::now())->first() : null;

            $offer = $service->first()->groups()->whereHas('offers', function ($q) {
                $q->active()->flash()->validFlashOffer();
            })->with(['offers' => function ($query) {
                $query->active()->flash()->validFlashOffer()->orderBy('end_date', 'desc');
            }])->first()->offers->first();
            
            $options = $this->serviceQuestionSet($service->first());
            $answers = collect();
            if ($options)
                foreach ($options as $option) {
                    $answers->push($option["answers"]);
                }

            $price_range = $approximatePriceCalculator->setService($service->first())->getMinMaxPartnerPrice();
            $service_max_price = $price_range[0] > 0 ? $price_range[0] : 0;
            $service_min_price = $price_range[1] > 0 ? $price_range[1] : 0;

            $service_breakdown = [];
            if ($options) {
                if (count($answers) > 1) {
                    $service_breakdown = $this->breakdown_service_with_min_max_price($answers, $service_min_price, $service_max_price);
                } else {
                    $total_breakdown = array();
                    foreach ($answers[0] as $index => $answer) {
                        $breakdown = array(
                            'name' => $answer,
                            'indexes' => array($index),
                            'min_price' => $service_min_price,
                            'max_price' => $service_max_price
                        );
                        array_push($total_breakdown, $breakdown);
                    }
                    $service_breakdown = $total_breakdown;
                }
            } else {
                $service_breakdown = array(array(
                    'name' => $service->first()->name,
                    'indexes' => null,
                    'min_price' => $service_min_price,
                    'max_price' => $service_max_price
                ));
            }

            $service = $request->has('is_business') ? $service->publishedForBusiness() : $service->publishedForAll();
            $service = $service->first();

            if ($service == null)
                return api_response($request, null, 404);
            if ($service->variable_type == 'Options') {
                $service['first_option'] = $this->serviceRepository->getFirstOption($service);
            }
            $scope = [];
            if ($request->has('scope')) {
                $scope = $this->serviceRepository->getServiceScope($request->scope);
            }
            if (in_array('discount', $scope) || in_array('start_price', $scope)) {
                $service = $this->serviceRepository->getpartnerServicePartnerDiscount($service);
            }
            if (in_array('reviews', $scope)) {
                $service->load('reviews');
            }
            $variables = json_decode($service->variables);
            unset($variables->max_prices);
            unset($variables->min_prices);
            unset($variables->prices);
            $services = [];
            array_push($services, $service);
            //$service = $this->serviceRepository->addServiceInfo($services, $scope)[0];
            $service['variables'] = $variables;
            $service['faqs'] = json_decode($service->faqs);
            $service['structured_description'] = $service->structured_description ? json_decode($service->structured_description) : null;

            $service['bn_faqs'] = $service->bn_faqs ? json_decode($service->bn_faqs) : null;
            $category = Category::with(['parent' => function ($query) {
                $query->select('id', 'name');
            }])->where('id', $service->category_id)->select('id', 'name', 'parent_id', 'video_link', 'slug')->first();

            array_add($service, 'category_name', $category->name);
            array_add($service, 'video_link', $category->video_link);
            array_add($service, 'category_slug', $category->slug);
            array_add($service, 'master_category_id', $category->parent->id);
            array_add($service, 'master_category_name', $category->parent->name);
            array_add($service, 'service_breakdown', $service_breakdown);
            if (config('sheba.online_payment_discount_percentage') > 0) {
                $discount_percentage = config('sheba.online_payment_discount_percentage');
                $payment_discount_percentage = "Save $discount_percentage% more by paying online after checkout!";
                array_add($service, 'payment_discount_percentage', $payment_discount_percentage);
            }
            if ($offer) {
                array_add($service, 'is_flash', $offer->is_flash);
                array_add($service, 'start_time', $offer->start_date->toDateTimeString());
                array_add($service, 'end_time', $offer->end_date->toDateTimeString());
            } else {
                array_add($service, 'is_flash', 0);
                array_add($service, 'start_time', null);
                array_add($service, 'end_time', null);
            }

            if ($request->has('is_business')) {
                $questions = null;
                $service['type'] = 'normal';
                if ($service->variable_type == 'Options') {
                    $questions = $service->variables->options;
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
                array_add($service, 'questions', $questions);
                array_add($service, 'faqs', $service->faqs);
            }

            return api_response($request, $service, 200, ['service' => $service]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    private function serviceQuestionSet($service)
    {
        $questions = null;
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
        return $questions;
    }

    private function breakdown_service_with_min_max_price($arrays, $min_price, $max_price, $i = 0)
    {
        if (!isset($arrays[$i])) {
            return array();
        }

        if ($i == count($arrays) - 1) {
            return $arrays[$i];
        }

        $tmp = $this->breakdown_service_with_min_max_price($arrays, $min_price, $max_price, $i + 1);

        $result = array();

        foreach ($arrays[$i] as $array_index => $v) {
            foreach ($tmp as $index => $t) {
                $result[] = is_array($t) ?
                    array(
                        'name' => $v . " - " . $t['name'],
                        'indexes' => array_merge(array($array_index), $t['indexes']),
                        'min_price' => $t['min_price'],
                        'max_price' => $t['max_price'],
                    ) :
                    array(
                        'name' => $v . " - " . $t,
                        'indexes' => array($array_index, $index),
                        'min_price' => $min_price,
                        'max_price' => $max_price
                    );
            }
        }
        return $result;
    }

    public function checkForValidity($service, Request $request)
    {
        $service = Service::where('id', $service)->published()->first();
        return $service != null ? api_response($request, true, 200) : api_response($request, false, 404);
    }

    public function getReviews($service)
    {
        $service = Service::with(['reviews' => function ($q) {
            $q->select('id', 'service_id', 'partner_id', 'customer_id', 'review_title', 'review', 'rating', DB::raw('DATE_FORMAT(updated_at, "%M %d, %Y at %h:%i:%s %p") as time'))
                ->with(['partner' => function ($q) {
                    $q->select('id', 'name', 'status', 'sub_domain');
                }])->with(['customer' => function ($q) {
                    $q->select('id', 'profile_id')->with(['profile' => function ($q) {
                        $q->select('id', 'name');
                    }]);
                }])->orderBy('updated_at', 'desc');
        }])->select('id')->where('id', $service)->first();
        if (count($service->reviews) > 0) {
            $service = $this->reviewRepository->getGeneralReviewInformation($service);
            $breakdown = $this->reviewRepository->getReviewBreakdown($service->reviews);
            $service = $this->reviewRepository->filterReviews($service);
            return response()->json(['msg' => 'ok', 'code' => 200, 'service' => $service, 'breakdown' => $breakdown]);
        }
        return response()->json(['msg' => 'not found', 'code' => 404]);
    }

    public function getPrices($service)
    {
        $service = Service::find($service);
        $prices = $this->serviceRepository->getMaxMinPrice($service);
        return response()->json(['max' => $prices[0], 'min' => $prices[1], 'code' => 200]);
    }

    private function resolveInputTypeField($answers)
    {
        $answers = explode(',', $answers);
        return count($answers) <= 4 ? "radiobox" : "dropdown";
    }
}
