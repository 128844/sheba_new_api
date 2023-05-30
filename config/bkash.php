<?php

return [
    '01799444000' => [
        'app_key' => env('BKASH_APP_KEY_017'),
        'app_secret' => env('BKASH_APP_SECRET_017'),
        'username' => env('BKASH_USERNAME_017'),
        'password' => env('BKASH_PASSWORD_017'),
        'url' => env('BKASH_URL_017')
    ],
    '01833309595'=>[
        'app_key'=>env('BKASH_APP_KEY_MARKETPLACE'),
        'app_secret'=>env('BKASH_APP_SECRET_MARKETPLACE'),
        'username'=>env('BKASH_USERNAME_MARKETPLACE'),
        'password'=>env('BKASH_PASSWORD_MARKETPLACE'),
        'url'=>env('BKASH_URL_MARKETPLACE')
    ],
    '01833922030' => [
        'app_key' => env('BKASH_APP_KEY_018'),
        'app_secret' => env('BKASH_APP_SECRET_018'),
        'username' => env('BKASH_USERNAME_018'),
        'password' => env('BKASH_PASSWORD_018'),
        'url' => env('BKASH_URL_018')
    ],
    'old_username' => env('BKASH_VERIFICATION_USERNAME'),
    'old_password' => env('BKASH_VERIFICATION_PASSWORD'),
    'verification_endpoint' => env('BKASH_VERIFICATION_ENDPOINT', 'https://www.bkashcluster.com:9081/dreamwave/merchant/trxcheck/sendmsg'),
    'merchant_number' => env('BKASH_MERCHANT_NUMBER', '01799444000'),
    'tokenized' => [
        'app_key' => env('BKASH_TOKENIZED_APP_KEY'),
        'app_secret' => env('BKASH_TOKENIZED_APP_SECRET'),
        'username' => env('BKASH_TOKENIZED_USERNAME'),
        'password' => env('BKASH_TOKENIZED_PASSWORD'),
        'url' => env('BKASH_TOKENIZED_URL')
    ],
    'payout' => [
        'app_key' => env('BKASH_PAYOUT_APP_KEY'),
        'app_secret' => env('BKASH_PAYOUT_APP_SECRET'),
        'username' => env('BKASH_PAYOUT_USERNAME'),
        'password' => env('BKASH_PAYOUT_PASSWORD'),
        'url' => env('BKASH_PAYOUT_URL')
    ]
];