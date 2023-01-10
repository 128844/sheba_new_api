<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessMember;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Sheba\Business\BusinessBasicInformation;
use Sheba\Dal\BusinessMemberAdditionalField\BusinessMemberAdditionalField;
use Sheba\Dal\BusinessMemberAdditionalField\BusinessMemberAdditionalFieldRepository;
use Sheba\Dal\BusinessMemberAdditionalSection\BusinessMemberAdditionalSection;

class AdditionalInformationController extends Controller
{
    use BusinessBasicInformation;

    public function index(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $this->getBusiness($request);
        if (!$business) return api_response($request, null, 404);

        $tabs = $business->memberAdditionalSections->map(function (BusinessMemberAdditionalSection $section) {
            return [
                'id' => $section->id,
                'key' => $section->name,
                'label' => $section->label
            ];
        })->toArray();

        return api_response($request, null, 200, [
            'tabs' => $tabs
        ]);
    }

    public function show(Request $request, $section_id, BusinessMemberAdditionalFieldRepository $repo)
    {
        /** @var Business $business */
        $business = $this->getBusiness($request);
        if (!$business) return api_response($request, null, 404);

        /** @var BusinessMember $businessMember */
        $businessMember = $this->getBusinessMember($request);
        if (!$businessMember) return api_response($request, null, 404);

        $section = BusinessMemberAdditionalSection::find($section_id);
        if (!$section) return api_response($request, null, 404);

        if ($section->business_id != $business->id)  return api_response($request, null, 404);

        $fields = $repo->getBySectionWithValuesOfBusinessMember($section, $businessMember)
            ->map(function (BusinessMemberAdditionalField $field) {
                return [
                    'id' => $field->id,
                    'type' => $field->type,
                    'key' => $field->name,
                    'label' => $field->label,
                    'rules' => $field->rules ? json_decode($field->rules, 1) : null,
                    'value' => $field->value
                ];
            })->toArray();

        return api_response($request, null, 200, [
            'data' => $fields
        ]);
    }

    public function update(Request $request, $section_id, BusinessMemberAdditionalFieldRepository $repo)
    {
        /** @var Business $business */
        $business = $this->getBusiness($request);
        if (!$business) return api_response($request, null, 404);

        /** @var BusinessMember $businessMember */
        $businessMember = $this->getBusinessMember($request);
        if (!$businessMember) return api_response($request, null, 404);

        $section = BusinessMemberAdditionalSection::find($section_id);
        if (!$section) return api_response($request, null, 404);
        if ($section->business_id != $business->id)  return api_response($request, null, 404);

        $repo->updateValuesOfBusinessMemberBySection($section, $businessMember, $request->data);

        return api_response($request, null, 200, [
            'message' => "Data updated successfully"
        ]);
    }
}
