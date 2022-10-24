<?php

return [
    'non_uniform_gateway_ref_last_id' => env('NON_UNIFORM_GATEWAY_REF_LAST_ID'),
    'robi'                            => [
        'url'          => env('ROBI_TOPUP_URL'),
        'login_id'     => env('ROBI_TOPUP_LOGIN'),
        'password'     => env('ROBI_TOPUP_PASSWORD'),
        'gateway_code' => env('ROBI_TOPUP_GATEWAY_CODE'),
        'robi_mid'     => env('ROBI_TOPUP_ROBI_MID'),
        'robi_pin'     => env('ROBI_TOPUP_ROBI_PIN'),
        'airtel_mid'   => env('ROBI_TOPUP_AIRTEL_MID'),
        'airtel_pin'   => env('ROBI_TOPUP_AIRTEL_PIN')
    ],
    'bl'                              => [
        'url'          => env('BL_TOPUP_URL'),
        'login_id'     => env('BL_TOPUP_LOGIN'),
        'password'     => env('BL_TOPUP_PASSWORD'),
        'gateway_code' => env('BL_TOPUP_GATEWAY_CODE'),
        'mid'          => env('BL_TOPUP_MID'),
        'pin'          => env('BL_TOPUP_PIN')
    ],
    'ssl'                             => [
        'proxy_url'       => env('SSL_VR_PROXY_URL'),
        'client_id'       => env('SSL_TOPUP_CLIENT_ID'),
        'client_password' => env('SSL_TOPUP_CLIENT_PASSWORD'),
        'url'             => env('SSL_TOPUP_URL'),
    ],
    'status'                          => [
        'initiated'  => ['sheba' => 'Initiated', 'partner' => 'Initiated', 'customer' => 'Initiated'],
        'pending'    => ['sheba' => 'Pending', 'partner' => 'Active', 'customer' => 'Verified'],
        'successful' => ['sheba' => 'Successful', 'partner' => 'Inactive', 'customer' => 'Inactive'],
        'failed'     => ['sheba' => 'Failed', 'partner' => 'Inactive', 'customer' => 'Blocked'],
    ],
    'paywell'                         => [
        'username'          => env('PAYWELL_USERNAME'),
        'auth_password'     => env('PAYWELL_AUTH_PASSWORD'),
        'password'          => env('PAYWELL_PASSWORD'),
        'get_token_url'     => env('PAYWELL_GET_TOKEN_URL'),
        'api_key'           => env('PAYWELL_API_KEY'),
        'encryption_key'    => env('PAYWELL_ENCRYPTION_KEY'),
        'single_topup_url'  => env('PAYWELL_SINGLE_TOPUP_URL'),
        'topup_enquiry_url' => env('PAYWELL_TOPUP_ENQUIRY_URL'),
    ],
    'bd_recharge'                     => [
        'username'            => env('BD_RECHARGE_USERNAME'),
        'password'            => env('BD_RECHARGE_PASSWORD'),
        'single_topup_url'    => env('BD_RECHARGE_SINGLE_TOPUP_URL'),
        'topup_enquiry_url'   => env('BD_RECHARGE_TOPUP_ENQUIRY_URL'),
        'balance_enquiry_url' => env('BD_RECHARGE_BALANCE_ENQUIRY_URL'),
        'jwe_header'          => [
            "zip" => "DEF",
            "enc" => "A128CBC-HS256",
            "alg" => "dir",
            "kid" => env('BD_RECHARGE_KEY_ID'),
        ],
        'key'                 => [
            'kty' => 'oct',
            'kid' => env('BD_RECHARGE_KEY_ID'),
            'use' => 'enc',
            'alg' => 'A128CBC-HS256',
            'k'   => env('BD_RECHARGE_ENCRYPTION_KEY'),
        ]
    ],
    'pay_station'                     => [
        'base_url_v1' => env('PAY_STATION_V1_BASE_URL'),
        'base_url'    => env('PAY_STATION_V2_BASE_URL'),
        'user_name'   => env('PAY_STATION_USER_NAME'),
        'password'    => env('PAY_STATION_PASSWORD'),
        'auth_number' => env('PAY_STATION_AUTH_NUMBER'),
    ]
];
