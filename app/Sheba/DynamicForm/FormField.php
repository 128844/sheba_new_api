<?php

namespace App\Sheba\DynamicForm;

use Illuminate\Contracts\Support\Arrayable;
use Sheba\Helpers\BasicGetter;

class FormField implements Arrayable
{
    use BasicGetter;

    private $input_type = '';
    private $label = '';
    private $message = '';
    private $hint = '';
    private $id = '';
    private $error = '';
    private $data = '';
    private $min_length = '';
    private $max_length = '';
    private $is_editable = false;
    private $mandatory = false;
    private $data_source = '';
    private $data_source_id = '';
    private $regex = '';

    public function setFormInput($input): FormField
    {
        foreach ($input as $key => $value) {
            if (isset($this->$key)) {
                $this->$key = $value;
            }
        }

        return $this;
    }

    /**
     * @param $value
     * @return FormField
     */
    public function setData($value): FormField
    {
        $this->data = $value;
        return $this;
    }

    public function toArray(): array
    {
        return [
            "input_type"  => $this->input_type,
            "label"       => $this->label,
            "message"     => $this->message,
            "hint"        => $this->hint,
            "id"          => $this->id,
            "error"       => $this->error,
            "data"        => $this->data,
            "min_length"  => $this->min_length,
            "max_length"  => $this->max_length,
            "is_editable" => $this->is_editable,
            "mandatory"   => $this->mandatory,
            "regex"       => json_decode($this->regex),
        ];
    }
}
