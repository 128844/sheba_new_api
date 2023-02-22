<?php namespace App\Sheba\MTB\Validation;

use App\Models\Partner;
use App\Sheba\DynamicForm\CategoryDetails;
use App\Sheba\DynamicForm\CompletionCalculation;
use App\Sheba\DynamicForm\DynamicForm;
use App\Sheba\DynamicForm\FormFieldBuilder;
use App\Sheba\MtbOnboarding\MtbSavePrimaryInformation;
use Sheba\Dal\MefForm\Model as MefForm;
use Sheba\Dal\MefSections\Model as MefSection;

class ApplyValidation
{
    private $form;
    /**
     * @var Partner
     */
    private $partner;
    private $section;

    public function setForm($form_id): ApplyValidation
    {
        $this->form = MefForm::find($form_id);
        return $this;
    }

    public function setPartner(Partner $partner): ApplyValidation
    {
        $this->partner = $partner;
        return $this;
    }

    public function setSection($section_id): ApplyValidation
    {
        $this->section = MefSection::find($section_id);
        return $this;
    }

    public function getSectionFields(): array
    {
        $fields = array();
        $form_builder = (new FormFieldBuilder())->setPartner($this->partner);
        foreach ($this->section->fields as $field)
            $fields[] = $form_builder->setField($field)->build()->toArray();
        return $fields;
    }

    public function getFormSections(): float
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
        return (new CompletionCalculation())->getFinalCompletion($categories);
    }
}
