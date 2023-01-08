<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CustomInformationController extends Controller
{
    public function getTabs(Request $request)
    {
        $custom_tabs = [
            [
                'key' => 'additional',
                'label' => 'Additional Information'
            ]
        ];

        return api_response($request, null, 200, [
            'tabs' => $custom_tabs
        ]);
    }
}
