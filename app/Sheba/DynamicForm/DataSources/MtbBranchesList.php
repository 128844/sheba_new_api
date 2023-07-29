<?php

namespace App\Sheba\DynamicForm\DataSources;

use Sheba\Helpers\ConstGetter;

class MtbBranchesList
{
    use ConstGetter;

    const thakurgaon_branch = ['name' => "Thakurgaon Branch", 'branch_code' => 58];
    const shahparan_gate_branch = ['name' => "Shahparan Gate Branch", 'branch_code' => 59];


    public static function getGeneratedKeyNameValue(): array
    {
        $branches_name = self::getWithKeys();
        $banks_name_by_key_name_value_pair = [];
        $index = 1;

        foreach ($branches_name as $key => $branch_name) {
            $banks_name_by_key_name_value_pair[] = [
                "key" => $index++,
                "name" => $branch_name['name'],
                "value" => $branch_name['branch_code']
            ];
        }

        return $banks_name_by_key_name_value_pair;
    }
}