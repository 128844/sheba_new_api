<?php

return [
    'actions' => [
        'trip_request' => [
            'auto_assign' => 'trip_request_auto_assign',
            'rw' => 'trip_request_rw'
        ],
        'inspection' => [
            'rw' => 'inspection_rw'
        ],
        'form_template' => [
            'rw' => 'form_template_rw'
        ],
        'inspection_item' => [
            'rw' => 'inspection_item_rw'
        ],
        'inspection_issue' => [
            'rw' => 'inspection_issue_rw'
        ],
        'procurement' => [
            'rw' => 'procurement_rw',
            'r' => 'procurement_r',
        ],
        'announcement' => [
            'rw' => 'announcement_rw',
        ],
        'support' => [
            'rw' => 'announcement_rw',
        ],
        'expense' => [
            'rw' => 'announcement_rw',
        ],
    ],
    'DRIVER_CONTRACT_TYPES' => ['permanent', 'temporary'],
    'VEHICLE_TYPES' => ['hatchback', 'sedan', 'suv', 'passenger_van', 'others'],
    'INSPECTION_TYPE' => [
        'one_time' => 'One Time',
        'monthly' => 'Monthly',
        'weekly' => 'Weekly',
    ]
];