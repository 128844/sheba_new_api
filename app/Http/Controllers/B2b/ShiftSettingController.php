<?php namespace App\Http\Controllers\B2b;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShiftCreateOrUpdateRequest;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Transformers\Business\ShiftDetailsTransformer;
use App\Transformers\Business\ShiftListTransformer;
use App\Transformers\CustomSerializer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Serializer\ArraySerializer;
use Sheba\Business\ShiftSetting\Creator as ShiftSettingCreator;
use Sheba\Business\ShiftSetting\Requester as ShiftSettingRequest;
use Sheba\Business\ShiftSetting\Updater as ShiftSettingUpdater;
use Sheba\Dal\BusinessShift\BusinessShiftRepository;
use Sheba\ModificationFields;

class ShiftSettingController extends Controller
{
    use ModificationFields;

    /** * @var BusinessShiftRepository */
    private $businessShiftRepository;
    /*** @var ShiftSettingUpdater */
    private $shiftUpdater;

    public function __construct(ShiftSettingUpdater $shift_updater, BusinessShiftRepository $business_shift_repository)
    {
        $this->shiftUpdater = $shift_updater;
        $this->businessShiftRepository = $business_shift_repository;
    }

    public function index(Request $request)
    {
        /** @var Business $business */
        $business = $request->business;
        if (!$business) return api_response($request, null, 401);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);

        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $shifts = new Collection($business->shifts()->withCount(['assignments' => function($query) {
            $query->select(DB::raw('count(distinct(business_member_id))'));
        }])->get(), new ShiftListTransformer());
        $shifts = collect($manager->createData($shifts)->toArray()['data']);
        return api_response($request, $shifts, 200, ['shift' => $shifts]);
    }

    public function create(ShiftCreateOrUpdateRequest $request, ShiftSettingCreator $shift_creator)
    {
        /** @var Business $business */
        $business = $request->business;
        if (!$business) return api_response($request, null, 401);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);

        $this->setModifier($business_member->member);

        $shift_create_request = $request->buildRequest()->setBusiness($business);
        $shift_create_request->validate();

        if ($shift_create_request->hasError()) {
            return api_response($request, null, $shift_create_request->getErrorCode(), [
                'message' => $shift_create_request->getErrorMessage()
            ]);
        }

        $shift_creator->setShiftRequester($shift_create_request)->create();
        return api_response($request, null, 200);
    }

    public function delete($business, $id, Request $request)
    {
        /** @var Business $business */
        $business = $request->business;
        if (!$business) return api_response($request, null, 401);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);

        $business_shift = $this->businessShiftRepository->find($id);
        if (!$business_shift) return api_response($request, null, 404);

        $this->shiftUpdater->setShiftRequester((new ShiftSettingRequest())->setShift($business_shift))->softDelete();

        return api_response($request, null, 200);
    }

    public function details($business, $id, Request $request)
    {
        /** @var Business $business */
        $business = $request->business;
        if (!$business) return api_response($request, null, 401);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);

        $business_shift = $this->businessShiftRepository->find($id);
        if (!$business_shift) return api_response($request, null, 404);

        $manager = new Manager();
        $manager->setSerializer(new CustomSerializer());

        $business_shift->assignments_count = $business_shift->assignments()->count(DB::raw('distinct(business_member_id)'));

        $business_shift = $manager
            ->createData(new Item($business_shift, new ShiftDetailsTransformer()))
            ->toArray()['data'];
        return api_response($request, $business_shift, 200, ['shift_details' => $business_shift]);
    }

    public function updateColor($business, $id, ShiftCreateOrUpdateRequest $request)
    {
        /** @var Business $business */
        $business = $request->business;
        if (!$business) return api_response($request, null, 401);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);

        $business_shift = $this->businessShiftRepository->find($id);
        if (!$business_shift) return api_response($request, null, 404);

        $update_request = $request->buildRequest()->setShift($business_shift);
        $this->shiftUpdater->setShiftRequester($update_request)->updateColor();

        return api_response($request, null, 200);
    }

    public function updateShift($business, $id, ShiftCreateOrUpdateRequest $request)
    {
        /** @var Business $business */
        $business = $request->business;
        if (!$business) return api_response($request, null, 401);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);

        $business_shift = $this->businessShiftRepository->find($id);
        if (!$business_shift) return api_response($request, null, 404);

        $this->setModifier($business_member->member);

        $shift_update_request = $request->buildRequest()->setBusiness($business)->setShift($business_shift);
        $shift_update_request->validate();
        if ($shift_update_request->hasError()) {
            return api_response($request, null, $shift_update_request->getErrorCode(), [
                'message' => $shift_update_request->getErrorMessage()
            ]);
        }

        $this->shiftUpdater->setShiftRequester($shift_update_request)->update();
        return api_response($request, null, 200);
    }

    public function getColor($business, Request $request)
    {
        /** @var Business $business */
        $business = $request->business;
        if (!$business) return api_response($request, null, 401);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);

        return api_response($request, null, 200, ['colors' => config('b2b.SHIFT_COLORS')]);
    }
}
