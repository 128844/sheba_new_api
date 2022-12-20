<?php

namespace App\Sheba\OrderPlace\Exceptions;

class CategoryLocationNotFoundException extends \Exception
{
    protected $message = 'Category location not found.';
}