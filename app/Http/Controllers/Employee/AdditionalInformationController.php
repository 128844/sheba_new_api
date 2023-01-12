<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Presenters\BusinessMemberAdditionalSectionsPresenter as SectionsPresenter;
use App\Http\Presenters\BusinessMemberAdditionalFieldsPresenter as FieldsPresenter;
use App\Models\Business;
use App\Models\BusinessMember;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Sheba\Business\BusinessBasicInformation;
use Sheba\Dal\BusinessMemberAdditionalSection\BusinessMemberAdditionalSection as Section;
use Sheba\Business\BusinessMember\AdditionalInformation\AdditionalInformationManager;
use Sheba\ModificationFields;

class AdditionalInformationController extends Controller
{
    use ModificationFields, BusinessBasicInformation;

    /** @var AdditionalInformationManager */
    private $additionalInfo;

    public function __construct(AdditionalInformationManager $additionalInfoManager)
    {
        $this->additionalInfo = $additionalInfoManager;
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $this->getBusiness($request);
        if (!$business) return api_response($request, null, 404);

        return api_response($request, null, 200, [
            'tabs' => (new SectionsPresenter($business->memberAdditionalSections))->toArray()
        ]);
    }

    public function show(Request $request, $section_id)
    {
        /** @var Business $business */
        $business = $this->getBusiness($request);
        if (!$business) return api_response($request, null, 404);

        /** @var BusinessMember $businessMember */
        $businessMember = $this->getBusinessMember($request);
        if (!$businessMember) return api_response($request, null, 404);

        $section = Section::find($section_id);
        if (!$section || !$section->isOfBusiness($business)) return api_response($request, null, 404);

        $fields = $this->additionalInfo->getFieldsForBusinessMember($section, $businessMember);

        return api_response($request, null, 200, [
            'data' => (new FieldsPresenter($fields))->toArray()
        ]);
    }

    public function update(Request $request, $section_id)
    {
        /** @var Business $business */
        $business = $this->getBusiness($request);
        if (!$business) return api_response($request, null, 404);

        /** @var BusinessMember $businessMember */
        $businessMember = $this->getBusinessMember($request);
        if (!$businessMember) return api_response($request, null, 404);

        $this->setModifier($businessMember->member);

        $section = Section::find($section_id);
        if (!$section || !$section->isOfBusiness($business)) return api_response($request, null, 404);

        $this->additionalInfo->updateDataForBusinessMember($section, $businessMember, $request->all());

        return api_response($request, null, 200, [
            'message' => "Data updated successfully"
        ]);
    }
}
