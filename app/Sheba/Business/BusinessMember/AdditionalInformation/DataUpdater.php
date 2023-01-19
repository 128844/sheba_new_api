<?php

namespace Sheba\Business\BusinessMember\AdditionalInformation;

use App\Models\BusinessMember;
use Illuminate\Http\UploadedFile;
use Sheba\Dal\BusinessMemberAdditionalField\BusinessMemberAdditionalFieldRepository;
use Sheba\Dal\BusinessMemberAdditionalSection\BusinessMemberAdditionalSection as Section;
use Sheba\Dal\BusinessMemberAdditionalField\BusinessMemberAdditionalField as Field;
use Sheba\FileManagers\CdnFileManager;
use Sheba\FileManagers\FileManager;

class DataUpdater
{
    use CdnFileManager, FileManager;

    /** @var BusinessMemberAdditionalFieldRepository $fieldRepo */
    private $fieldRepo;

    public function __construct(BusinessMemberAdditionalFieldRepository $fieldRepo)
    {
        $this->fieldRepo = $fieldRepo;
    }

    public function update(Section $section, BusinessMember $businessMember, $data)
    {
        $fields = $this->fieldRepo->getBySectionWithValuesOfBusinessMember($section, $businessMember);

        $errors = $this->validate($fields, $data);
        if (count($errors)) throw new AdditionalInformationValidationException($errors);

        $this->prepareAndSave($fields, $data, $businessMember);
    }

    private function validate($fields, $data)
    {
        $errors = [];
        $fields->each(function (Field $field) use ($data, &$errors) {
            if (!array_key_exists($field->id, $data)) return;

            $value = $data[$field->id];

            if ($field->hasPossibleValues()) $this->validatePossibleValues($field, $errors, $value);
            if ($field->isFile()) $this->validateFile($field, $errors, $value);
            if ($field->isNumeric()) $this->validateNumeric($field, $errors, $value);
        });
        return $errors;
    }

    private function validatePossibleValues(Field $field, &$errors, $value)
    {
        if (!$field->isCheckbox()) {
            $this->validateSinglePossibleValues($field, $errors, $value);
            return;
        }

        if (!is_array($value)) {
            $errors[$field->id] = $field->label . " must be an array.";
            return;
        }

        foreach ($value as $item) {
            $this->validateSinglePossibleValues($field, $errors, $item);
        }
    }

    private function validateSinglePossibleValues(Field $field, &$errors, $value)
    {
        if ($field->matchesPossibleValues($value)) return;

        $errors[$field->id] = $field->label . " must be one of " . $field->implodePossibleValueLabels();
    }

    private function validateFile(Field $field, &$errors, $value)
    {
        if ($value instanceof UploadedFile && $field->matchesFileExtension($value)) return;

        $errors[$field->id] = $field->label . " must be a file and extension must be one of " . $field->implodeFileExtensions();
    }

    private function validateNumeric(Field $field, &$errors, $value)
    {
        if (is_numeric($value)) return;

        $errors[$field->id] = $field->label . " must be a number.";
    }

    private function prepareAndSave($fields, $data, BusinessMember $businessMember)
    {
        $fields->each(function (Field $field) use ($data, $businessMember) {
            if (!array_key_exists($field->id, $data)) return;

            $value = $data[$field->id];
            if ($value instanceof UploadedFile) {
                $value = $this->uploadFile($field, $value, $businessMember);
            }
            if (is_array($value)) {
                $value = json_encode($value);
            }

            $this->upsert($field, $value, $businessMember);
        });
    }

    private function uploadFile(Field $field, $value, BusinessMember $businessMember)
    {
        $destinationPath = getBusinessMemberAdditionalFileFolder();
        $filename = $this->uniqueFileName($value, $businessMember->member->profile->name . "_" . $field->name);
        return $this->saveFileToCDN($value, $destinationPath, $filename);
    }

    private function upsert(Field $field, $value, BusinessMember $businessMember)
    {
        if ($field->data_id) {
            $this->fieldRepo->updateData($field, $field->data_id, $value);
        } else {
            $this->fieldRepo->createData($field, $businessMember, $value);
        }
    }
}
