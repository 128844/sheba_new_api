<?php

namespace Sheba\Business\BusinessMember\AdditionalInformation;

use Sheba\Dal\BusinessMemberAdditionalField\BusinessMemberAdditionalFieldRepository;
use Sheba\Dal\BusinessMemberAdditionalSection\BusinessMemberAdditionalSection as Section;
use Sheba\Dal\BusinessMemberAdditionalField\BusinessMemberAdditionalField as Field;
use Sheba\Dal\BusinessMemberAdditionalField\FieldTypes;

class FieldUpdater
{
    /** @var BusinessMemberAdditionalFieldRepository $fieldRepo */
    private $fieldRepo;

    public function __construct(BusinessMemberAdditionalFieldRepository $fieldRepo)
    {
        $this->fieldRepo = $fieldRepo;
    }

    public function upsert(Section $section, $data)
    {
        $fields = $this->fieldRepo->getBySection($section)->toAssocFromKey(function (Field $field) {
            return $field->id;
        });

        $dataIds = [];
        foreach ($data as $datum) {
            if ($datum['id'] != null && $fields->has($datum['id'])) {
                $dataIds[] = $datum['id'];
                $this->updateField($fields->get($datum['id']), $datum);
            } else {
                $this->createField($section, $datum);
            }
        }

        $removedIds = array_diff($fields->keys()->toArray(), $dataIds);
        $this->fieldRepo->deleteByIds($removedIds);
    }

    private function createField(Section $section, $data)
    {
        $createData = $this->formatDataForDB($data);
        $createData['section_id'] = $section->id;
        $this->fieldRepo->create($createData);
    }

    private function updateField(Field $field, $data)
    {
        $updateData = $this->formatDataForDB($data);
        $this->deleteMismatchedData($field, $updateData);
        $this->fieldRepo->update($field, $updateData);
    }

    private function formatDataForDB($data)
    {
        $rules = [];

        if ($data['possible_values'] != null) {
            $rules['possible_values'] = array_map(function ($possible_value) {
                return [
                    "key" => str_slug($possible_value, '_'),
                    "label" => $possible_value
                ];
            }, $data['possible_values']);
        }

        if ($data['type'] == FieldTypes::FILE) {
            $rules['extensions'] = ["png", "jpeg", "jpg", "docx", "pdf"];
        }

        return [
            'type' => $data['type'],
            'label' => $data['label'],
            'name' => str_slug($data['label'], '_'),
            'rules' => count($rules) ? json_encode($rules) : null
        ];
    }

    private function deleteMismatchedData(Field $field, $data)
    {
        if ($field->type != $data['type']) {
            $this->fieldRepo->deleteAllData($field);
            return;
        }

        if (!$field->hasPossibleValues()) return;

        $newPossibleValues = array_map(function ($possibleValue) {
            return $possibleValue['key'];
        }, json_decode($data['rules'], 1)['possible_values']);
        $removePossibleValues = array_diff($field->getPossibleValueKeys(), $newPossibleValues);
        $this->fieldRepo->deleteAllDataWithValues($field, $removePossibleValues);
    }
}
