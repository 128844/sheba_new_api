<?php namespace App\Http\Controllers\B2b;

use App\Models\BusinessJoinRequest;
use App\Models\Partner;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\BusinessMember;
use Sheba\ModificationFields;
use Illuminate\Http\Request;
use App\Models\Business;
use App\Models\Member;
use Carbon\Carbon;
use DB;
use Sheba\Sms\Sms;

class BusinessesController extends Controller
{
    use ModificationFields;
    private $sms;

    public function __construct(Sms $sms)
    {
        $this->sms = $sms;
    }

    public function inviteVendors($business, Request $request)
    {
        try {
            $this->validate($request, [
                'numbers' => 'required|json'
            ]);

            $business = $request->business;
            $this->setModifier($business);

            foreach (json_decode($request->numbers) as $number) {

                $mobile = formatMobile($number);
                if ($partner = $this->hasPartner($mobile)) {
                    $partner->businesses()->sync(['business_id' => $business->id]);
                } else {
                    $data = [
                        'business_id' => $business->id,
                        'mobile' => $mobile
                    ];
                    BusinessJoinRequest::create($data);
                    $this->sms->shoot($number, "You have been invited to serve corporate client. Just click the link- http://bit.ly/ShebaManagerApp . sheba.xyz will help you to grow and manage your business. by $business->name");
                }
            }
            return api_response($request, 1, 200);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getVendorsList($business, Request $request)
    {
        try {
            $business = $request->business;
            $partners = $business->partners()->with('categories')->select('id', 'name', 'mobile', 'logo')->get();
            $vendors = collect();
            if ($business) {
                foreach ($partners as $partner) {
                    $master_categories = collect();
                    $partner->categories->map(function ($category) use ($master_categories) {
                        $parent_category = $category->parent()->select('id', 'name')->first();
                        $master_categories->push($parent_category);
                    });
                    $master_categories = $master_categories->unique()->pluck('name');
                    $vendor = [
                        "id" => $partner->id,
                        "name" => $partner->name,
                        "logo" => $partner->logo,
                        "mobile" => $partner->mobile,
                        'type' => $master_categories
                    ];
                    $vendors->push($vendor);
                }
                return api_response($request, $vendors, 200, ['vendors' => $vendors]);
            } else {
                return api_response($request, 1, 404);
            }
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getVendorInfo($business, $vendor, Request $request)
    {
        try {
            $business = $request->business;
            $partner = Partner::find((int)$vendor);
            $basic_informations = $partner->basicInformations;
            $resources = $partner->resources->count();
            $type = $partner->businesses->pluck('type')->unique();

            $master_categories = collect();
            $partner->categories->map(function ($category) use ($master_categories) {
                $parent_category = $category->parent()->select('id', 'name')->first();
                $master_categories->push($parent_category);
            });
            $master_categories = $master_categories->unique()->pluck('name');

            $vendor = [
                "id" => $partner->id,
                "name" => $partner->name,
                "logo" => $partner->logo,
                "mobile" => $partner->mobile,
                "company_type" => $type,
                "service_type" => $master_categories,
                "no_of_resource" => $resources,
                "trade_license" => $basic_informations->trade_license,
                "establishment_year" => $basic_informations->trade_license ? Carbon::parse($basic_informations->trade_license)->format('M, Y') : null,
            ];

            return api_response($request, $vendor, 200, ['vendor' => $vendor]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
}
