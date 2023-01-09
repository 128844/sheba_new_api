<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Sheba\Business\BusinessBasicInformation;
use Sheba\Dal\BusinessMemberAdditionalField\BusinessMemberAdditionalField;
use Sheba\Dal\BusinessMemberAdditionalSection\BusinessMemberAdditionalSection;

class AdditionalInformationController extends Controller
{
    use BusinessBasicInformation;

    public function index(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $this->getBusiness($request);

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

    public function show(Request $request, $section_id)
    {
        $section = BusinessMemberAdditionalSection::find($section_id);
        if (!$section) return api_response($request, null, 404);

        $fields = $section->fields->map(function (BusinessMemberAdditionalField $field) {
            return [
                'id' => $field->id,
                'type' => $field->type,
                'key' => $field->name,
                'label' => $field->label,
                'rules' => $field->rules ? json_decode($field->rules, 1) : null,
                'value' => null
            ];
        })->toArray();

        return api_response($request, null, 200, [
            'data' => $fields
        ]);
    }
}
