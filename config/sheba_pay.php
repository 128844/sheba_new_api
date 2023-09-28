<?php
return [
    'auth' => [
        'username' => env('SHEBA_PAY_USERNAME', 'shebaPay'),
        'password' => env("SHEBA_PAY_PASSWORD", 'ShebaPayAdmin#1'),
    ],
    'accounts'=>[
        'api_key'=>env('SHEBA_PAY_ACCOUNTS_API_KEY','ChQAOSK65_SN2oX2VH-9EQ')
    ]
];