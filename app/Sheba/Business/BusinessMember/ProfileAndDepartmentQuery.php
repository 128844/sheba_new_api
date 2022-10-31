<?php

namespace Sheba\Business\BusinessMember;

class ProfileAndDepartmentQuery
{
    public $profileColumns = ['name', 'mobile', 'email', 'pro_pic'];
    public $department = null;
    public $searchTerm = null;

    public function addProfileColumns($columns)
    {
        $this->profileColumns = array_merge($this->profileColumns, $columns);
        return $this;
    }
}
