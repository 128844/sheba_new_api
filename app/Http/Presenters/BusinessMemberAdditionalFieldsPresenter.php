<?php

namespace App\Http\Presenters;

use Illuminate\Support\Collection;
use Sheba\Dal\BusinessMemberAdditionalField\BusinessMemberAdditionalField as Field;

class BusinessMemberAdditionalFieldsPresenter extends Presenter
{
    private $fields;

    public function __construct(Collection $fields)
    {
        $this->fields = $fields;
    }

    public function toArray()
    {
        return $this->fields->map(function (Field $field) {
            return [
                'id' => $field->id,
                'type' => $field->type,
                'key' => $field->name,
                'label' => $field->label,
                'rules' => $field->getRulesWithCheckedBox(),
                'value' => $field->value,
                'display_value' => $field->getValueLabel()
            ];
        })->toArray();
    }
}
