<?php

namespace App\Sheba\DynamicForm\DataSources;

use Sheba\Helpers\ConstGetter;

class AccountType
{
    use ConstGetter;

    const single_signatory = "Single Signatory";
    const joint_signatory = "Joint Signatory";

//    public $typeName;
//
//    public function __construct()
//    {
//        $this->typeName = $this->getWithKeys();
//    }

    public static function getGeneratedKeyNameValue(): array
    {
        $types_name = self::getWithKeys();
        $types_name_by_key_name_value_pair = [];
        $index = 1;

        foreach ($types_name as $key => $type_name) {
            $types_name_by_key_name_value_pair[] = [
                "key" => $index++,
                "name" => $key,
                "value" => $type_name
            ];
        }

        return $types_name_by_key_name_value_pair;
    }
}