<?php

return [
    'FORM_TEMPLATES' => [
        'inspection' => 'inspection',
        'purchase_request' => 'purchase_request',
        'procurement' => 'procurement'
    ],
    'PURCHASE_REQUEST_STATUS' => [
        'pending' => 'pending',
        'approved' => 'approved',
        'rejected' => 'rejected',
        'need_approval' => 'need_approval'
    ],
    'PURCHASE_REQUEST_TYPE' => [
        'product' => 'product',
        'service' => 'service'
    ],
    'PURCHASE_REQUEST_APPROVAL_TYPE' => [
        'pending' => 'pending',
        'approved' => 'approved',
        'rejected' => 'rejected',
        'need_approval' => 'need_approval'
    ],
    'PROCUREMENT_TYPE' => [
        'basic' => 'basic',
        'advance' => 'advance',
        'product' => 'product',
        'service' => 'service'
    ],
    'PROCUREMENT_ITEM_TYPE' => [
        'price_quotation' => 'price_quotation',
        'technical_evaluation' => 'technical_evaluation',
        'company_evaluation' => 'company_evaluation'
    ],
    'PROCUREMENT_STATUS' => [
        'pending' => 'pending',
        'approved' => 'approved',
        'rejected' => 'rejected',
        'need_approval' => 'need_approval',
        'accepted' => 'accepted',
        'started' => 'started',
        'served' => 'served',
        'cancelled' => 'cancelled'
    ],
    'PROCUREMENT_ITEM_FIELD' => [
        'text' => 'text',
        'textarea' => 'textarea',
        'radio' => 'radio',
        'checkbox' => 'checkbox',
        'number' => 'number',
        'select' => 'select'
    ],
    'BID_STATUSES' => [
        'pending' => 'pending',
        'rejected' => 'rejected',
        'accepted' => 'accepted',
        'drafted' => 'drafted',
        'sent' => 'sent',
        'awarded' => 'awarded'
    ],
    'PROCUREMENT_PAYMENT_STATUS' => [
        'pending' => 'pending',
        'approved' => 'approved',
        'acknowledged' => 'acknowledged',
        'rejected' => 'rejected',
        'paid' => 'paid'
    ],
    'TRIP_REQUEST_APPROVAL_STATUS' => [
        'pending' => 'pending',
        'accepted' => 'accepted',
        'rejected' => 'rejected'
    ],
    'SHARING_TO' => [
        'public' => [
            'key' => 'public',
            'value' => 'Public',
        ],
        'verified' => [
            'key' => 'verified',
            'value' => 'Verified',
        ],
        'own_listed' => [
            'key' => 'own_listed',
            'value' => 'Listed/Own',
        ]
    ],
    'TENDER_POST_TYPE' => [
        'public' => [
            'key' => 'public',
            'value' => 'Public Posts',
        ],
        'verified' => [
            'key' => 'verified',
            'value' => 'Sheba Verified Posts',
        ]
    ],
    'PAYMENT_STRATEGY' => [
        '100% Payment after Delivery',
        '50% Advance Payment',
        '100% Advance Payment'
    ],
    'NUMBER_OF_PARTICIPANTS' => [
        0 => [
            'key' => 0,
            'value' => 'Unlimited',
        ],
        1 => [
            'key' => 1,
            'value' => 'One',
        ],
        2 => [
            'key' => 2,
            'value' => 'Two',
        ],
        3 => [
            'key' => 3,
            'value' => 'Three',
        ],
        4 => [
            'key' => 4,
            'value' => 'Four',
        ],
        5 => [
            'key' => 5,
            'value' => 'Five',
        ],
    ],
    'BUSINESSES_IDS_FOR_LUNCH' => [
        110, 1416, 1274
    ],
    'BUSINESSES_LUNCH_LINK' => 'https://docs.google.com/forms/d/1MeRaVlL34-n6YQHIt_l_50nDkQHnyt5h8XpqSJPUbqA/viewform?edit_requested=true',
    'BUSINESSES_IDS_FOR_REFERRAL' => [
        110, 1416, 1274
    ],
    'SHIFT_COLORS' => [
        '#12B886',
        '#4373F5',
        '#FF8F5E',
        '#E75050',
        '#8A21F3',
        '#38C8E7',
        '#A6BF4B',
        '#F2C53D',
        '#027D8E',
        '#A47F4F',
        '#BF7069',
        '#532559',
        '#0F47D2',
        '#F638AB',
        '#31C5B4',
        '#FFF000',
        '#D0E0E3',
        '#FF1100',
        '#FFC100',
        '#00F0FF'
    ]
];
