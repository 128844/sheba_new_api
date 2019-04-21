<?php namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PartnerPosService;
use App\Models\PartnerPosServiceDiscount;

use App\Transformers\PosServiceTransformer;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use League\Fractal\Serializer\ArraySerializer;
use Sheba\Pos\Product\Creator as ProductCreator;
use Sheba\Pos\Product\Updater as ProductUpdater;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sheba\ModificationFields;
use Sheba\Pos\Repositories\PosServiceDiscountRepository;

class ServiceController extends Controller
{
    use ModificationFields;

    public function index(Request $request)
    {
        try {
            $partner = $request->partner;
            $services = [];
            $base_query = PartnerPosService::with('discounts');

            if ($request->has('category_id') && !empty($request->category_id)) {
                $category_ids = explode(',', $request->category_id);
                $base_query->whereIn('pos_category_id', $category_ids);
            }

                $base_query->select($this->getSelectColumnsOfService())
                ->partner($partner->id)->get()
                ->each(function ($service) use (&$services) {
                    $services[] = [
                        'id'                    => $service->id,
                        'name'                  => $service->name,
                        'app_thumb'             => $service->app_thumb,
                        'app_banner'            => $service->app_banner,
                        'price'                 => $service->price,
                        'stock'                 => $service->stock,
                        'discount_applicable'   => $service->discount() ? true : false,
                        'discounted_price'      => $service->discount() ? $service->getDiscountedAmount() : 0
                    ];
            });
            if (!$services) return api_response($request, null, 404);

            return api_response($request, $services, 200, ['services' => $services]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function show(Request $request)
    {
        try {
            $service = PartnerPosService::with(['category', 'discounts'])->find($request->service);
            if (!$service) return api_response($request, null, 404, ['msg' => 'Service Not Found']);

            $manager = new Manager();
            $manager->setSerializer(new ArraySerializer());
            $resource = new Item($service, new PosServiceTransformer());
            $service = $manager->createData($resource)->toArray();

            return api_response($request, $service, 200, ['service' => $service]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function store(Request $request, ProductCreator $creator)
    {
        try {
            $this->validate($request, ['name' => 'required', 'category_id' => 'required', 'price' => 'required']);
            $this->setModifier($request->partner);
            $partner_pos_service = $creator->setData($request->all())->create();

            if ($request->has('discount_amount') && $request->discount_amount > 0) {
                $discount_data = [
                    'amount' => (double)$request->discount_amount,
                    'start_date' => Carbon::now(),
                    'end_date' => Carbon::parse($request->end_date . ' 23:59:59')
                ];

                $partner_pos_service->discounts()->create($this->withCreateModificationField($discount_data));
            }
            return api_response($request, null, 200, ['msg' => 'Product Created Successfully', 'service' => $partner_pos_service]);
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

    public function update(Request $request, ProductUpdater $updater, PosServiceDiscountRepository $discount_repo)
    {
        try {
            $this->setModifier($request->partner);
            $partner_pos_service = PartnerPosService::find($request->service);
            if (!$partner_pos_service) return api_response($request, null, 400, ['msg' => 'Service Not Found']);
            $updater->setService($partner_pos_service)->setData($request->all())->update();

            if ($request->discount_id) {
                $discount_data = [];
                $discount = PartnerPosServiceDiscount::find($request->discount_id);
                if ($request->has('is_discount_off') && $request->is_discount_off) {
                    $discount_data = ['end_date' => Carbon::now()];
                } else {
                    $requested_end_date = ($request->has('end_date')) ? Carbon::parse($request->end_date . ' 23:59:59') : $discount->end_date;
                    if ($request->has('end_date') && !$requested_end_date->isSameDay($discount->end_date)) {
                        $discount_data['end_date'] = $requested_end_date;
                    }

                    if ($request->has('discount_amount') && $request->discount_amount != $discount->amount) {
                        $discount_data['amount'] = (double)$request->discount_amount;
                    }
                }

                if (!empty($discount_data)) $discount_repo->update($discount, $discount_data);
            }

            return api_response($request, null, 200, ['msg' => 'Product Updated Successfully', 'service' => $partner_pos_service]);
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

    private function getSelectColumnsOfService()
    {
        return ['id', 'name', 'app_thumb', 'app_banner', 'price', 'stock'];
    }
}
