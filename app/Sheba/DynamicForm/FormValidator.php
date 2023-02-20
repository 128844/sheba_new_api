<?php

namespace App\Sheba\DynamicForm;

use App\Sheba\DynamicForm\Exceptions\FormValidationException;
use Carbon\Carbon;
use Sheba\Dal\MefFields\Model as MefFields;
use Throwable;

class FormValidator
{
    /*** @var MefFields */
    private $fields;
    private $postData;

    /**
     * @param  mixed  $fields
     * @return FormValidator
     */
    public function setFields($fields): FormValidator
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * @param  mixed  $postData
     * @return FormValidator
     */
    public function setPostData($postData): FormValidator
    {
        $this->postData = json_decode($postData, 1);
        return $this;
    }

    /**
     * @return void
     * @throws FormValidationException
     */
    public function validate()
    {
        foreach ($this->fields as $field) {
            $form_field = (new FormField())->setFormInput(json_decode($field->data));
            if ($form_field->mandatory) {
                if (!isset($this->postData[$form_field->id])) {
                    throw new FormValidationException($form_field->error);
                }
            }
            if ($form_field->input_type === 'date') {
                try {
                    if (isset($this->postData[$form_field->id])) {
                        Carbon::parse($this->postData[$form_field->id]);
                    }
                } catch (Throwable $e) {
                    throw new FormValidationException($form_field->id." date is Invalid");
                }
            } elseif ($form_field->input_type === 'email') {
                $email = trim($this->postData[$form_field->id]);
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new FormValidationException($form_field->id." invalid email");
                }
            }
        }
    }
}