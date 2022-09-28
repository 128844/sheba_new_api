<?php

namespace App\Sheba\DynamicForm;

use App\Models\District;
use App\Models\Division;
use App\Models\Partner;
use App\Models\Thana;
use Illuminate\Support\Facades\DB;
use Sheba\Dal\MefForm\Model as MefForm;
use Sheba\Dal\MefSections\Model as MefSection;
use Sheba\MerchantEnrollment\MerchantEnrollmentFileHandler;
use Sheba\MerchantEnrollment\Statics\PaymentMethodStatics;
use Maatwebsite\Excel\Facades\Excel;

class DynamicForm
{
    /*** @var MefForm */
    private $form;

    /*** @var MefSection */
    private $section;

    /*** @var Partner */
    private $partner;

    private $requestData;
    private $type;
    private $formKey;

    /**
     * @var PartnerMefInformation
     */
    private $partnerMefInformation;

    public function setForm(): DynamicForm
    {
        $this->form = MefForm::where('key', $this->formKey)->first();
        return $this;
    }

    public function __construct(PartnerMefInformation $partnerMefInformation)
    {
        $this->partnerMefInformation = $partnerMefInformation;
    }

    public function getFormSections(): array
    {
        $categories = $this->sectionDetails();
        $finalCompletion = (new CompletionCalculation())->getFinalCompletion($categories);

        return (new SectionListResponse())->setCategories($categories)->setPartner($this->partner)
            ->setMessage(PaymentMethodStatics::dynamicCompletionPageMessage($this->formKey))
            ->setOverallCompletion($finalCompletion)->setCanApply($finalCompletion)->toArray();
    }

    private function sectionDetails(): array
    {
        $categories = array();
        foreach ($this->form->sections as $section) {
            $this->setSection($section->id);
            $fields = $this->getSectionFields();
            $completion = (new CompletionCalculation())->setFields($fields)->calculate();

            $categories[] = (new CategoryDetails())->setCategoryCode($section->key)
                ->setCompletionPercentage($completion)->setCategoryId($section->id)
                ->setTitle($section->name, $section->bn_name)->toArray();

        }
        return $categories;
    }

    public function getSectionDetails(): array
    {
        $fields = $this->getSectionFields();
        return [
            "title" => $this->getSectionNames(),
            "form_items" => $fields,
            "completion" => $this->getSectionCompletion($fields),
            "post_url" => $this->getPostUrl()
        ];
    }

    private function getSectionCompletion($fields): array
    {
        $completion = (new CompletionCalculation())->setFields($fields)->calculate();
        return [
            "en" => $completion,
            "bn" => convertNumbersToBangla($completion, false),
        ];
    }

    private function getPostUrl(): string
    {
        return config('sheba.api_url') . $this->section->post_url;
    }

    /**
     * @return void
     * @throws Exceptions\FormValidationException
     */
    public function postSectionFields()
    {
        (new FormValidator())->setFields($this->section->fields)->setPostData($this->requestData)->validate();
        (new FormSubmit())->setPartner($this->partner)->setFields($this->section->fields)->setPostData($this->requestData)->store();
    }

    private function getSectionNames()
    {
        return (new CategoryDetails())->setTitle($this->section->name, $this->section->bn_name)->getTitle();
    }

    public function getSectionFields(): array
    {
        $fields = array();
        $form_builder = (new FormFieldBuilder())->setPartner($this->partner);

        foreach ($this->section->fields as $field)
            $fields[] = $form_builder->setField($field)->build()->toArray();
        return $fields;
    }

    /**
     * @param mixed $partner
     * @return DynamicForm
     */
    public function setPartner($partner): DynamicForm
    {
        $this->partner = $partner;
        return $this;
    }

    /**
     * @param mixed $section_id
     * @return DynamicForm
     */
    public function setSection($section_id): DynamicForm
    {
        $this->section = MefSection::find($section_id);
        return $this;
    }

    /**
     * @param mixed $requestData
     * @return DynamicForm
     */
    public function setRequestData($requestData): DynamicForm
    {
        $this->requestData = $requestData;
        return $this;
    }

    /**
     * @param $type
     * @return DynamicForm
     */
    public function setType($type): DynamicForm
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @param mixed $formKey
     * @return DynamicForm
     */
    public function setFormKey($formKey): DynamicForm
    {
        $this->formKey = $formKey;
        $this->setForm();
        return $this;
    }

    private function getDivision()
    {
        $divisionInformation = json_decode(file_get_contents(public_path() . "/mtbThana.json"));
        $filtered_array = array();
        foreach ($divisionInformation as $value) {
            if (!in_array($value, $filtered_array)) {
                $filtered_array[] = $value;
            }
        }
        return $filtered_array;
    }

    private function getDistrict($division)
    {
        $thanaInformation = json_decode(file_get_contents(public_path() . "/mtbThana.json"));
        $filtered_array = array();
        foreach ($thanaInformation as $value) {
            if (ucfirst(strtolower($value->division)) == $division) {
                $filtered_array[] = $value;
            }
        }
        return $filtered_array;
    }

    private function getThana($district)
    {
        $thanaInformation = json_decode(file_get_contents(public_path() . "/mtbThana.json"));
        $filtered_array = array();
        foreach ($thanaInformation as $value) {
            if ($value->district == $district) {
                $filtered_array[] = $value;
            }
        }
        return $filtered_array;
    }

    public function typeData($request)
    {
        if ($request->header('version-code') < 300602) {
            if ($this->type == "division") {
                $division = Division::get();
                $division = (new CollectionFormatter())->setData($division)->formatCollection();
                $data = [
                    'division' => ['list' => $division]
                ];
                return $data['division'];
            }
            if ($this->type == "district") {
                if ($request->division) {
                    $district = District::where('division_id', $request->division)->orderBy('name', 'ASC')->get();
                    $district = (new CollectionFormatter())->setData($district)->formatCollection();
                    $data = [
                        'district' => ['list' => $district]
                    ];
                    return $data['district'];
                }
                $district = District::get();
                $district = (new CollectionFormatter())->setData($district)->formatCollection();
                $data = [
                    'district' => ['list' => $district]
                ];
                return $data['district'];
            }
            if ($this->type == "thana") {
                if ($request->district) {
                    $thana = Thana::where('district_id', $request->district)->orderBY('name', 'ASC')->get();
                    $thana = (new CollectionFormatter())->setData($thana)->formatCollection();
                    $data = [
                        'thana' => ['list' => $thana]
                    ];
                    return $data['thana'];
                }
                $thana = Thana::get();
                $thana = (new CollectionFormatter())->setData($thana)->formatCollection();
                $data = [
                    'thana' => ['list' => $thana]
                ];
                return $data['thana'];
            }
            if ($this->type == "tradeLicenseExists") {
                return config('trade_license');
            }
            if ($this->type == "nomineeRelation") {
                return config('mtb_nominee_relation');
            }
        }
        if ($this->type == "division") {
            $division = $this->getDivision();
            $division = (new CollectionFormatter())->setData($division)->formatCollectionUpdated();
            $final = array();
            foreach ($division as $current) {
                if (!in_array($current, $final)) {
                    $final[] = $current;
                }
            }
            $data = [
                'division' => ['list' => $final]
            ];
            return $data['division'];
        }
        if ($this->type == "district") {
            $district = $this->getDistrict(ucfirst(strtolower($request->division)));
            $district = (new CollectionFormatter())->setData($district)->formatCollectionDistrict();
            $final = array();
            foreach ($district as $current) {
                if (!in_array($current, $final)) {
                    $final[] = $current;
                }
            }

            $data = [
                'district' => ['list' => $final]
            ];
            return $data['district'];
        }
        if ($this->type == "thana") {
            $district = $this->getThana($request->district);
            $district = (new CollectionFormatter())->setData($district)->formatCollectionThana();
            $data = [
                'district' => ['list' => $district]
            ];
            return $data['district'];
        }
        if ($this->type == "tradeLicenseExists") {
            return config('trade_license');
        }
        if ($this->type == "nomineeRelation") {
            return config('mtb_nominee_relation');
        }
    }


    public function uploadDocumentData($document, $document_id)
    {
        $field = DB::table('fields')->where('data->id', $document_id)->first();
        $url = (new MerchantEnrollmentFileHandler())->setPartner($this->partner)->uploadDocument($document, json_decode($field->data, true))->getUploadedUrl();
        (new FormSubmit())->setPartner($this->partner)->setFields($field)->documentStore($url);
    }
}
