<?php

namespace App\Http\Controllers\B2b;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessMember;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Sheba\Business\BusinessBasicInformation;
use Sheba\Dal\BusinessMemberAdditionalSection\BusinessMemberAdditionalSection as Section;
use Sheba\Dal\BusinessMemberAdditionalField\BusinessMemberAdditionalField as Field;
use Sheba\Business\BusinessMember\AdditionalInformation\AdditionalInformationManager;
use Sheba\Dal\BusinessMemberAdditionalField\FieldTypes;
use Sheba\ModificationFields;
use Sheba\Repositories\Interfaces\BusinessMemberRepositoryInterface;
use App\Http\Presenters\BusinessMemberAdditionalSectionsPresenter as SectionsPresenter;
use App\Http\Presenters\BusinessMemberAdditionalFieldsPresenter as FieldsPresenter;

class AdditionalInformationController extends Controller
{
    use ModificationFields, BusinessBasicInformation;

    /** @var AdditionalInformationManager */
    private $additionalInfo;

    /** @var BusinessMemberRepositoryInterface */
    private $businessMemberRepo;

    public function __construct(AdditionalInformationManager $additionalInfoManager, BusinessMemberRepositoryInterface $businessMemberRepo)
    {
        $this->additionalInfo = $additionalInfoManager;
        $this->businessMemberRepo = $businessMemberRepo;
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->business;

        $data = $this->additionalInfo->getAdditionalSectionFieldsOfBusiness($business)->map(function (Field $field) {
            return [
                "id" => $field->id,
                "is_required" => false,
                "options" => $field->hasPossibleValues() ? $field->getPossibleValueLabels() : [],
                "question" => $field->label,
                "type" => $field->isString() ? "text" : $field->type
            ];
        });

        return api_response($request, null, 200, [
            'fields' => $data
        ]);
    }

    public function upsert(Request $request)
    {
        /** @var Business $business */
        $business = $request->business;
        /** @var Member $manager_member */
        $manager_member = $request->manager_member;
        $this->setModifier($manager_member);

        $data = array_map(function ($item) {
            return [
                "id" => array_key_exists('id', $item) ? $item['id'] : null,
                "label" => $item['question'],
                "type" => $item['type'] == 'text' ? FieldTypes::STRING : $item['type'],
                "possible_values" => count($item['options']) ? $item['options'] : null
            ];
        }, $request->fields);

        $this->additionalInfo->upsertAdditionalSectionOfBusiness($business, $data);

        return api_response($request, null, 200, [
            'message' => "Data updated successfully"
        ]);
    }

    public function sections(Request $request, $business, $businessMemberId)
    {
        /** @var Business $business */
        $business = $request->business;

        /** @var BusinessMember $businessMember */
        $businessMember = $this->businessMemberRepo->find($businessMemberId);
        if (!$businessMember || !$businessMember->isWithBusiness($business)) return api_response($request, null, 404);

        return api_response($request, null, 200, [
            'tabs' => (new SectionsPresenter($business->memberAdditionalSections))->toArray()
        ]);
    }

    public function sectionDetails(Request $request, $business, $businessMemberId, $sectionId)
    {
        /** @var Business $business */
        $business = $request->business;

        /** @var BusinessMember $businessMember */
        $businessMember = $this->businessMemberRepo->find($businessMemberId);
        if (!$businessMember || !$businessMember->isWithBusiness($business)) return api_response($request, null, 404);

        $section = Section::find($sectionId);
        if (!$section || !$section->isOfBusiness($business)) return api_response($request, null, 404);

        $fields = $this->additionalInfo->getFieldsForBusinessMember($section, $businessMember);

        return api_response($request, null, 200, [
            'data' => (new FieldsPresenter($fields))->toArray()
        ]);
    }

    public function updateData(Request $request, $business, $businessMemberId, $sectionId)
    {
        /** @var Business $business */
        $business = $request->business;

        /** @var BusinessMember $businessMember */
        $businessMember = $this->businessMemberRepo->find($businessMemberId);
        if (!$businessMember || !$businessMember->isWithBusiness($business)) return api_response($request, null, 404);

        $section = Section::find($sectionId);
        if (!$section || !$section->isOfBusiness($business)) return api_response($request, null, 404);

        $this->additionalInfo->updateDataForBusinessMember($section, $businessMember, $request->all());

        return api_response($request, null, 200, [
            'message' => "Data updated successfully"
        ]);
    }
}
