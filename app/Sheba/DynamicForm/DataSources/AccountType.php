<?php

namespace App\Sheba\DynamicForm\DataSources;

use Sheba\Helpers\ConstGetter;

class AccountType
{
    use ConstGetter;

    const single_signatory = "Single Signatory";
    const joint_signatory = "Joint Signatory";

    public $typeName;

    public function __construct()
    {
        $this->typeName = $this->getWithKeys();
    }
}