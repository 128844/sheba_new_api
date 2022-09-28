<?php

namespace App\Sheba\DynamicForm;

use Sheba\Dal\MefFields\Model as MefFields;

class CompletionCalculation
{
    const HEADER = "header";
    const SPOUSE_NAME = "spouseName";
    const TIN_NO = "tin_no";

    /*** @var MefFields */
    private $fields;

    private $finalCompletion;

    /**
     * @param mixed $fields
     * @return CompletionCalculation
     */
    public function setFields($fields): CompletionCalculation
    {
        $this->fields = $fields;
        return $this;
    }

    public function calculate()
    {
        $total = 0;
        $filled = 0;
        foreach ($this->fields as $field) {
            if ($field['input_type'] !== self::HEADER && $field['id'] !== self::SPOUSE_NAME && $field['id'] !== self::TIN_NO) {
                $total++;

                if (!empty($field["data"])) $filled++;
            }
        }

        return $total === 0 ? 100 : round(($filled / $total) * 100, 2);
    }

    public function getFinalCompletion($categories): float
    {
        $total = 0;
        foreach ($categories as $category) {
            $total += ($category['completion_percentage']['en']);
        }

        if (count($categories) === 0) return 100;

        return round($total / count($categories), 2);
    }
}
