<?php namespace App\Http\Controllers\B2b;

use App\Models\Attachment;
use App\Models\Business;
use App\Models\HyperLocal;
use App\Sheba\Business\ACL\AccessControl;
use App\Sheba\Business\BusinessBasicInformation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\BusinessMember;
use Sheba\Attachments\FilesAttachment;
use Sheba\FileManagers\FileManager;
use Sheba\Business\BusinessCommonInformationCreator;
use Sheba\Business\BusinessCreator;
use Sheba\Business\BusinessCreatorRequest;
use Sheba\Business\BusinessMember\Requester as BusinessMemberRequester;
use Sheba\Business\BusinessMember\Creator as BusinessMemberCreator;
use Sheba\Business\BusinessUpdater;
use Sheba\Business\CoWorker\Statuses;
use Sheba\Business\CoWorker\UpdaterV2 as Updater;
use Sheba\ModificationFields;
use Illuminate\Http\Request;
use App\Models\Member;
use Carbon\Carbon;
use Sheba\Repositories\Interfaces\MemberRepositoryInterface;
use Throwable;
use DB;

class MemberController extends Controller
{
    use ModificationFields, FilesAttachment, FileManager, BusinessBasicInformation;

    /** BusinessMemberRequester $businessMemberRequester */
    private $businessMemberRequester;
    /** BusinessMemberCreator $businessMemberCreator */
    private $businessMemberCreator;

    /**
     * MemberController constructor.
     * @param BusinessMemberRequester $business_member_requester
     * @param BusinessMemberCreator $business_member_creator
     */
    public function __construct(BusinessMemberRequester $business_member_requester, BusinessMemberCreator $business_member_creator)
    {
        $this->businessMemberRequester = $business_member_requester;
        $this->businessMemberCreator = $business_member_creator;
    }

    /**
     * @param $member
     * @param Request $request
     * @param BusinessCreatorRequest $business_creator_request
     * @param BusinessCreator $business_creator
     * @param BusinessUpdater $business_updater
     * @param BusinessCommonInformationCreator $common_info_creator
     * @return JsonResponse
     */
    public function updateBusinessInfo($member, Request $request,
                                       BusinessCreatorRequest $business_creator_request,
                                       BusinessCreator $business_creator,
                                       BusinessUpdater $business_updater,
                                       BusinessCommonInformationCreator $common_info_creator)
    {
        try {
            $this->validate($request, [
                'name' => 'required|string',
                'no_employee' => 'sometimes|required|integer',
                'lat' => 'sometimes|required|numeric',
                'lng' => 'sometimes|required|numeric',
                'address' => 'string',
                'mobile' => 'sometimes|required|string|mobile:bd',
                'company_logo' => 'file',
            ]);
            $member = Member::find($member);

            $this->setModifier($member);
            $business_creator_request = $business_creator_request->setName($request->name)
                ->setEmployeeSize($request->no_employee)
                ->setGeoInformation(json_encode(['lat' => (double)$request->lat, 'lng' => (double)$request->lng]))
                ->setAddress($request->address)
                ->setPhone($request->mobile);
            DB::beginTransaction();
            if (count($member->businesses) > 0) {
                $business = $member->businesses->first();
                $business_updater->setBusiness($business)->setBusinessCreatorRequest($business_creator_request)->update();
            } else {
                $business = $business_creator->setBusinessCreatorRequest($business_creator_request)->create();
                $common_info_creator->setBusiness($business)->setMember($member)->create();
                $this->createBusinessMember($business, $member);
            }
            if ($request->hasFile('company_logo')) {
                $file = $request->company_logo;
                $filename = $file->getClientOriginalName();
                $url = $this->saveFileToCDN($file, getBusinessLogoFolder(), $filename);
                $business_creator_request = $business_creator_request->setLogoUrl($url);
                $business_updater->setBusiness($business)->setBusinessCreatorRequest($business_creator_request)->updateLogo();
            }
            DB::commit();
            return api_response($request, null, 200, ['business_id' => $business->id]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (Throwable $e) {
            DB::rollback();
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param $business
     * @param $member
     * @return Model
     */
    private function createBusinessMember($business, $member)
    {
        $business_member_requester = $this->businessMemberRequester->setBusinessId($business->id)
            ->setMemberId($member->id)
            ->setStatus('active')
            ->setIsSuper(1)
            ->setJoinDate(Carbon::now());
        return $this->businessMemberCreator->setRequester($business_member_requester)->create();
    }

    /**
     * @param $member
     * @param Request $request
     * @return JsonResponse
     */
    public function getBusinessInfo($member, Request $request)
    {
        /** @var Business $business */
        $business = $request->business;
        if (!$business) return api_response($request, null, 404, ["message" => 'Business not found.']);

        $location = null;
        $geo_information = json_decode($business->geo_informations, 1);
        $hyperLocation = HyperLocal::insidePolygon((double)$geo_information['lat'], (double)$geo_information['lng'])->with('location')->first();
        if (!is_null($hyperLocation)) $location = $hyperLocation->location;

        $info = [
            "name" => $business->name,
            "sub_domain" => $business->sub_domain,
            "tagline" => $business->tagline,
            "company_type" => $business->type,
            'company_logo' => $this->isDefaultImageByUrl($business->logo) ? null : $business->logo,
            "address" => $business->address,
            "area" => $location ? $location->name : null,
            "geo_informations" => $geo_information,
            "wallet" => (double)$business->wallet,
            "employee_size" => $business->employee_size
        ];

        return api_response($request, null, 200, ['info' => $info]);
    }

    /**
     * @param $member
     * @param Request $request
     * @param AccessControl $access_control
     * @return JsonResponse
     */
    public function getMemberInfo($member, Request $request, AccessControl $access_control)
    {
        /** @var Member $member */
        $member = Member::find((int)$member);
        /** @var Business $business */
        $business = $member->businessMember ? $member->businessMember->business : null;
        $business_members = BusinessMember::where('member_id', $member->id)->get();

        if (!$business_members->isEmpty()) {
            $business_members = $business_members->reject(function ($business_member) {
                return $business_member->status == Statuses::INACTIVE;
            });
            if (!$business_members->count()) return api_response($request, null, 420, ['message' => 'You account deactivated from this company']);
        }

        $business_member = $member->activeBusinessMember->first();
        if (!$business_member) return api_response($request, null, 420, ['message' => 'You account is not active yet. Please contract with your company.']);
        $manager = $business ? $business->getActiveBusinessMember()->where('manager_id', $business_member->id)->count() : null;
        $is_manager = $manager ? 1 : 0;
        $profile = $member->profile;
        $access_control->setBusinessMember($business_member);

        $info = [
            'profile_id' => $profile->id,
            'business_member_id' => $business_member->id,
            'name' => !$this->isNull($profile->name) ? $profile->name : 'n/s',
            'mobile' => $business_member->mobile,
            'email' => $profile->email,
            'pro_pic' => $profile->pro_pic,
            'gender' => $profile->gender,
            'date_of_birth' => $profile->dob ? Carbon::parse($profile->dob)->format('M-j, Y') : null,
            'join_date' => $business_member->join_date ? Carbon::parse($business_member->join_date)->toDateString() : null,
            'nid_no' => $profile->nid_no,
            'address' => $profile->address,
            'business_id' => $business ? $business->id : null,
            'department' => ($business_member && $business_member->department()) ? $business_member->department()->id : null,
            'designation' => ($business_member && $business_member->role) ? $business_member->role->name : null,
            'is_super' => $business_member ? $business_member->is_super : null,
            'is_essential_info_available_for_activate' => $this->isEssentialInfoAvailableForActivate($business_member, $profile),
            'is_payroll_enable' => $business_member ? $business_member->is_payroll_enable : null,
            'is_shift_enable' => $business->is_shift_enable,
            'is_live_tracking_accessible' => $business_member ? $business_member->is_live_track_accessible : null,
            'remember_token' => $member->remember_token,
            'is_manager' => $is_manager,
            'access' => [
                'support' => $business ? (in_array($business->id, config('business.WHITELISTED_BUSINESS_IDS')) && $access_control->hasAccess('support.rw') ? 1 : 0) : 0,
                'expense' => $business ? (in_array($business->id, config('business.WHITELISTED_BUSINESS_IDS')) && $access_control->hasAccess('expense.rw') ? 1 : 0) : 0,
                'announcement' => $business ? (in_array($business->id, config('business.WHITELISTED_BUSINESS_IDS')) && $access_control->hasAccess('announcement.rw') ? 1 : 0) : 0
            ]
        ];

        return api_response($request, $info, 200, ['info' => $info]);
    }

    private function isEssentialInfoAvailableForActivate($business_member, $profile)
    {
        if ($this->isNull($profile->name) || !$profile->gender || !$business_member->business_role_id || !$business_member->join_date)
            return 0;
        return 1;
    }

    private function isNull($data)
    {
        if ($data == " ") return true;
        if ($data == null) return true;
        return false;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $list = [];
            $request->business->load('members.profile');
            $members = $request->business->members;
            foreach ($request->business->members as $member) {
                array_push($list, [
                        'id' => $member->id,
                        'name' => $member->profile->name
                    ]
                );
            }
            if (count($members) > 0) return api_response($request, $members, 200, ['members' => $list]);
            else  return api_response($request, null, 404);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param $member
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     */
    public function storeAttachment($member, Request $request)
    {
        try {
            $this->validate($request, [
                'file' => 'required'
            ]);

            $business_member = $request->business_member;
            $member = $request->member;
            $this->setModifier($member);
            $model = "App\\Models\\" . ucfirst(camel_case($request->type));
            $model = $model::find((int)$request->type_id);
            if (!$request->hasFile('file'))
                return redirect()->back();
            $data = $this->storeAttachmentToCDN($request->file('file'));
            $attachment = $model->attachments()->save(new Attachment($this->withBothModificationFields($data)));
            return api_response($request, $attachment, 200, ['attachment' => $attachment->file]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param $member
     * @param Request $request
     * @return JsonResponse
     */
    public function getAttachments($member, Request $request)
    {
        try {
            $business_member = $request->business_member;
            $member = $request->member;
            $model = "App\\Models\\" . ucfirst(camel_case($request->type));
            $model = $model::find((int)$request->type_id);
            if (!$model) return api_response($request, null, 404);
            list($offset, $limit) = calculatePagination($request);
            $attaches = Attachment::where('attachable_type', get_class($model))->where('attachable_id', $model->id)
                ->select('id', 'title', 'file', 'file_type')->orderBy('id', 'DESC')->skip($offset)->limit($limit)->get();
            $attach_lists = [];
            foreach ($attaches as $attach) {
                array_push($attach_lists, [
                    'id' => $attach->id,
                    'title' => $attach->title,
                    'file' => $attach->file,
                    'file_type' => $attach->file_type,
                ]);
            }

            if (count($attach_lists) > 0) return api_response($request, $attach_lists, 200, ['attach_lists' => $attach_lists]);
            else  return api_response($request, null, 404);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function updateMemberInfo($business, $member, Request $request, MemberRepositoryInterface $member_repository, Updater $profile_updater)
    {
        $validation_rules = [
            'name' => 'required|string',
            'email' => 'required|email|string'
        ];
        if ($request->has('mobile')) $validation_rules['mobile'] = 'string|mobile:bd';
        $this->validate($request, $validation_rules);

        /** @var Member $member */
        $member = $member_repository->find((int)$member);
        if (!$member) return api_response($request, null, 404);
        $business_member = $member->businessMember;
        if (!$business_member) return api_response($request, null, 404);
        $business = $business_member ? $business_member->business : null;
        if (!$business) return api_response($request, null, 404);

        $profile_updater->setBusinessMember($business_member)
            ->setName($request->name)
            ->setMobile($request->mobile)
            ->setEmail($request->email);

        if ($profile_updater->hasError()) return api_response($request, null, $profile_updater->getErrorCode(), ['message' => $profile_updater->getErrorMessage()]);

        $profile_updater->update();

        return api_response($request, null, 200);
    }
}
