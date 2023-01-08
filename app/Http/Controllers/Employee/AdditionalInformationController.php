<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdditionalInformationController extends Controller
{
    public function getTabs(Request $request): JsonResponse
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

    public function index(Request $request)
    {
        $fields = [
            'tab_key' => 'additional',
            'tab_label' => 'Additional Information',
            'fields' => [
                [
                    'id' => 1,
                    'order' => 1,
                    'type' => 'radio',
                    'key' => 'marital_status',
                    'label' => 'Marital Status',
                    'possible_values' => [
                        [
                            'key' => 'married',
                            'label' => 'Married'
                        ],
                        [
                            'key' => 'unmarried',
                            'label' => 'unmarried'
                        ]
                    ],
                    'value' => null
                ],
                [
                    'id' => 2,
                    'order' => 2,
                    'type' => 'string',
                    'key' => 'wife_count',
                    'label' => 'How Many Wife',
                    'value' => null
                ],
                [
                    'id' => 3,
                    'order' => 3,
                    'type' => 'checkbox',
                    'key' => 'language_skills',
                    'label' => 'Language Skills',
                    'possible_values' => [
                        [
                            'key' => 'bangla',
                            'label' => 'Bangla'
                        ],
                        [
                            'key' => 'english',
                            'label' => 'English'
                        ],
                        [
                            'key' => 'german',
                            'label' => 'German'
                        ]
                    ],
                    'value' => null
                ],
                [
                    'id' => 4,
                    'order' => 4,
                    'type' => 'dropdown',
                    'key' => 'hometown',
                    'label' => 'Home Town',
                    'possible_values' => [
                        [
                            'key' => 'dhaka',
                            'label' => 'Dhaka'
                        ],
                        [
                            'key' => 'sylhet',
                            'label' => 'Sylhet'
                        ],
                        [
                            'key' => 'rajshahi',
                            'label' => 'Rajshahi'
                        ],
                        [
                            'key' => 'barishal',
                            'label' => 'Barishal'
                        ],
                        [
                            'key' => 'chittagong',
                            'label' => 'Chittagong'
                        ],
                        [
                            'key' => 'khulna',
                            'label' => 'Khulna'
                        ],
                        [
                            'key' => 'rangpur',
                            'label' => 'Rangpur'
                        ],
                        [
                            'key' => 'mymensingh',
                            'label' => 'Mymensingh'
                        ]
                    ],
                    'value' => null
                ],
            ]
        ];

        return api_response($request, null, 200, [
            'data' => $fields
        ]);
    }
}
