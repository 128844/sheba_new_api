<?php

namespace App\Http\Controllers\DynamicForm;

use App\Http\Controllers\Controller;
use App\Sheba\DynamicForm\DynamicForm;
use App\Sheba\DynamicForm\Exceptions\FormValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormController extends Controller
{
    /**
     * @var DynamicForm
     */
    private $dynamicForm;

    /**
     * @param  DynamicForm  $form
     */
    public function __construct(DynamicForm $form)
    {
        $this->dynamicForm = $form;
    }

    /**
     * @param  Request  $request
     * @return JsonResponse
     */
    public function getSections(Request $request): JsonResponse
    {
        $this->validate($request, ["key" => "required"]);
        $partner = $request->auth_user->getPartner();
        $data = $this->dynamicForm->setPartner($partner)->setFormKey($request->key)->getFormSections();

        return http_response($request, null, 200, ['data' => $data]);
    }

    /**
     * @param  Request  $request
     * @return JsonResponse
     */
    public function getSectionWiseFields(Request $request): JsonResponse
    {
        $this->validate($request, ["key" => "required", "category_code" => "required"]);
        $partner = $request->auth_user->getPartner();
        $data = $this->dynamicForm->setFormKey($request->key)->setPartner($partner)->setSection($request->category_code)->getSectionDetails();

        return http_response($request, null, 200, ['data' => $data]);
    }

    /**
     * @param  Request  $request
     * @return JsonResponse
     * @throws FormValidationException
     */
    public function postSectionWiseFields(Request $request): JsonResponse
    {
        $this->validate($request, ["data" => "required|json", "key" => "required", "category_code" => "required"]);
        $partner = $request->auth_user->getPartner();

        $this->dynamicForm
            ->setFormKey($request->key)
            ->setRequestData($request->data)
            ->setPartner($partner)
            ->setSection($request->category_code)
            ->postSectionFields();

        return http_response($request, null, 200, ['message' => "Successful"]);
    }

    /**
     * @param  Request  $request
     * @return JsonResponse
     */
    public function selectTypes(Request $request): JsonResponse
    {
        $this->validate($request, ["type" => "required"]);
        $data = $this->dynamicForm->setType($request->type)->typeData($request);

        return http_response($request, null, 200, ['message' => 'Successful', 'data' => $data]);
    }

    /**
     * @param  Request  $request
     * @return JsonResponse
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $this->validate($request, ["document" => 'required|image|max:25600', "document_id" => 'required', "category_code" => "required"]);
        $partner = $request->auth_user->getPartner();
        $this->dynamicForm->setPartner($partner)->setSection($request->category_code)->uploadDocumentData($request->document, $request->document_id);
        return http_response($request, null, 200, ['message' => 'Successful']);
    }
}