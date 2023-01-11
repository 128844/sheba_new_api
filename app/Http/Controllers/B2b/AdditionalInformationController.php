<?php

namespace App\Http\Controllers\B2b;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessMember;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Sheba\Business\BusinessBasicInformation;
use Sheba\Dal\BusinessMemberAdditionalField\BusinessMemberAdditionalField as Field;
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
        $business = $request->business;
        if (!$business) return api_response($request, null, 404);

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
        if (!$business) return api_response($request, null, 404);
        /** @var Member $manager_member */
        $manager_member = $request->manager_member;
        $this->setModifier($manager_member);

        $data = array_map(function ($item) {
            return [
                "id" => array_key_exists('id', $item) ? $item['id'] : null,
                "label" => $item['question'],
                "type" => $item['type'] == 'text' ? "string" : $item['type'],
                "possible_values" => count($item['options']) ? $item['options'] : null
            ];
        }, $request->fields);

        $this->additionalInfo->upsertAdditionalSectionOfBusiness($business, $data);

        return api_response($request, null, 200, [
            'message' => "Data updated successfully"
        ]);
    }
}
