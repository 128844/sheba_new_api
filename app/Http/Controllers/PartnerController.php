<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Job;
use App\Models\Partner;
use App\Models\PartnerOrder;
use App\Repositories\ReviewRepository;
use App\Repositories\ServiceRepository;
use Illuminate\Http\Request;

use App\Http\Requests;

class PartnerController extends Controller
{
    private $serviceRepository;
    private $reviewRepository;

    public function __construct()
    {
        $this->serviceRepository = new ServiceRepository();
        $this->reviewRepository = new ReviewRepository();
    }

    public function index()
    {
        $partners = Partner::select('id', 'name', 'sub_domain', 'logo')->where('status', 'Verified')->orderBy('name')->get();
        return response()->json(['partners' => $partners, 'code' => 200, 'msg' => 'successful']);
    }

    public function getPartnerServices($partner)
    {
        $partner = Partner::select('id', 'name', 'sub_domain', 'description', 'logo', 'type', 'level')
            ->where('id', $partner)
            ->first();
        $review = $partner->reviews()->where('review', '<>', '')->count('review');
        $rating = round($partner->reviews()->avg('rating'), 1);
        if ($rating == 0) {
            $rating = 5;
        }
        $served_job_count = $partner->jobs()->where('status', 'Served')->count();
        $resource_count = $partner->resources()->where('resources.is_verified', 1)->count();

        array_add($partner, 'review', $review);
        array_add($partner, 'rating', $rating);
        array_add($partner, 'job_count', $served_job_count);
        array_add($partner, 'resource_count', $resource_count);

        $partner_services = $partner->services()
            ->select('services.id', 'services.banner', 'services.category_id', 'services.publication_status', 'name', 'variable_type')
            ->where([
                ['is_verified', 1],
                ['is_published', 1],
                ['services.publication_status', 1]
            ])->get();
        $count_of_partner_services = count($partner_services);
        array_add($partner, 'service_count', $count_of_partner_services);
        if ($count_of_partner_services > 6) {
            $partner_services = $partner_services->random(6);
        }
        $final_service = [];
        foreach ($partner_services as $service) {
            $service = $this->serviceRepository->getStartPrice($service);
            array_add($service, 'slug_service', str_slug($service->name, '-'));
            //review count of partner of this service
            $review = $service->reviews()->where([
                ['review', '<>', ''],
                ['partner_id', $partner->id]
            ])->count('review');
            //avg rating of the partner for this service
            $rating = $service->reviews()->where('partner_id', $partner->id)->avg('rating');
            array_add($service, 'review', $review);
            array_add($service, 'rating', $rating);
            array_forget($service, 'pivot');
            array_push($final_service, $service);
        }
//        $partner_categories = $partner->categories()->select('categories.id', 'name')->get();
//        foreach ($partner_categories as $category) {
//            $service = $partner_services->where('category_id', $category->id);
//            array_add($category, 'service', $service);
//            array_forget($category, 'pivot');
//        }

        if (count($partner) > 0) {
            return response()->json([
                'partner' => $partner,
                'services' => $final_service,
                'msg' => 'successful',
                'code' => 200
            ]);
        }
    }

    public function insert()
    {
        $partner_orders = PartnerOrder::where('closed_at', null)->get();
        foreach ($partner_orders as $partner_order) {
            $partner_order->calculate();
            if ($partner_order->status == 'Closed') {
                $dates = $partner_order->jobs()->select('delivered_date')->get();
                $partner_order->closed_at = $dates->max()->delivered_date;
                $partner_order->update();
            }
        }
    }

    public function getReviews($partner)
    {
        $partner = Partner::with(['reviews' => function ($q) {
            $q->select('id', 'service_id', 'partner_id', 'customer_id', 'review_title', 'review', 'rating', 'updated_at')
                ->with(['service' => function ($q) {
                    $q->select('id', 'name');
                }])
                ->with(['customer' => function ($q) {
                    $q->select('id', 'name');
                }])->orderBy('updated_at', 'desc');
        }])->select('id')->where('id', $partner)->first();
        if (count($partner->reviews) > 0) {
            $partner = $this->reviewRepository->getReviews($partner);
            $breakdown = $this->reviewRepository->getReviewBreakdown($partner->reviews);
            return response()->json(['msg' => 'ok', 'code' => 200, 'partner' => $partner, 'breakdown' => $breakdown]);
        }
        return response()->json(['msg' => 'not found', 'code' => 404]);
    }
}
