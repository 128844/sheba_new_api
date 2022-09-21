<?php

return [
    'cbl' => [
        'tunnel_url' => env('CBL_TUNNEL_HOST'),
        'merchant_id' => env('CBL_MERCHANT_ID'),
        'urls' => [
            'approve' => env('SHEBA_API_URL') . '/v2/payments/cbl/success',
            'decline' => env('SHEBA_API_URL') . '/v2/payments/cbl/fail',
            'cancel' => env('SHEBA_API_URL') . '/v2/payments/cbl/cancel',
        ]
    ],
    'ssl' => [
        'stores' => [
            'default' => [
                'id' => env('SSL_STORE_ID'),
                'password' => env('SSL_STORE_PASSWORD'),
                'session_url' => env('SSL_SESSION_URL'),
                'order_validation_url' => env('SSL_ORDER_VALIDATION_URL'),
            ],
            'market_place' => [
                'id' => env('SSL_MARKET_PLACE_STORE_ID'),
                'password' => env('SSL_MARKET_PLACE_STORE_PASSWORD'),
                'session_url' => env('SSL_MARKET_PLACE_SESSION_URL'),
                'order_validation_url' => env('SSL_MARKET_PLACE_ORDER_VALIDATION_URL'),
            ],
            'donation' => [
                'id' => env('SSL_DONATION_STORE_ID'),
                'password' => env('SSL_DONATION_STORE_PASSWORD'),
                'session_url' => env('SSL_DONATION_SESSION_URL'),
                'order_validation_url' => env('SSL_DONATION_ORDER_VALIDATION_URL'),
            ]
        ],
        'urls' => [
            'refund' => env('SSL_REFUND_URL'),
            'success' => env('SHEBA_API_URL') . '/v2/orders/payments/success',
            'fail' => env('SHEBA_API_URL') . '/v2/orders/payments/fail',
            'cancel' => env('SHEBA_API_URL') . '/v2/orders/payments/cancel',
            'api' => env('SSL_API_URL', 'https://sandbox.sslcommerz.com/validator/api')
        ]
    ],
    'shurjopay' => [
        'base_url' => env('SHURJOPAY_BASE_URL', 'https://sandbox.shurjopayment.com/api'),
    ],
    'port_wallet' => [
        'base_url' => env('PORT_WALLET_BASE_URL'),
        'app_key' => env('PORT_WALLET_APP_KEY'),
        'secret_key' => env('PORT_WALLET_SECRET_KEY'),
        'is_ipn_enabled' => env('PORT_WALLET_IPN_ENABLED', true),
        'urls' => [
            'ipn' => env('SHEBA_API_URL') . '/v2/payments/port-wallet/ipn',
            'validation_on_redirect' => env('SHEBA_API_URL') . '/v2/payments/port-wallet/validate-on-redirect',
            'redirect_without_validation' => env('SHEBA_API_URL') . '/v2/payments/port-wallet/redirect-without-validate',
        ]
    ],
    'ok_wallet' => [
        'base_url' => env('OK_WALLET_BASE_URL'),
        'api_user' => env('OK_WALLET_API_USER'),
        'api_password' => env('OK_WALLET_API_PASSWORD'),
        'merchant_id' => env('OK_WALLET_MERCHANT_ID'),
        'access_key' => env('OK_WALLET_ACCESS_KEY'),
        'web_client_base_url' => env('OK_WALLET_WEB_CLIENT_BASE_URL'),
        'urls' => [
            'approve' => env('SHEBA_API_URL') . '/v2/payments/ok-wallet/success',
            'decline' => env('SHEBA_API_URL') . '/v2/payments/ok-wallet/fail',
            'cancel' => env('SHEBA_API_URL') . '/v2/payments/ok-wallet/cancel',
        ]
    ],
    'nagad' => [
        'stores' => [
            'default' => [
                'base_url' => env('NAGAD_BASE_URL', 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0'),
                'merchant_id' => env('NAGAD_MERCHANT_ID', '683002007104225'),
                'context_path' => 'remote-payment-gateway-1.0'
            ],
            'affiliate' => [
                'base_url' => env('NAGAD_BASE_URL', 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0'),
                'merchant_id' => env('NAGAD_AFFILIATE_MERCHANT_ID', '683002007104225'),
                'context_path' => 'remote-payment-gateway-1.0'
            ],
            'marketplace' => [
                'base_url' => env('NAGAD_BASE_URL', 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0'),
                'merchant_id' => env('NAGAD_MARKETPLACE_MERCHANT_ID', '686200110675045'),
                'context_path' => 'remote-payment-gateway-1.0'
            ],
        ]
    ],
    'ebl' => [
        'auth_token' => env('EBL_APP_AUTH_TOKEN')
    ],
    'upay'        => [
        'stores'       => [
            'default' => [
                'merchant_id'               => env('UPAY_DEFAULT_MERCHANT_ID', '1110101010000002'),
                'merchant_key'              => env('UPAY_DEFAULT_MERCHANT_KEY', 'sdf7jk23489889234'),
                'merchant_name'             => env('UPAY_DEFAULT_MERCHANT_NAME', 'TEST5'),
                'merchant_country_code'     => 'BD',
                'merchant_city'             => 'Dhaka',
                'merchant_category_code'    => env('UPAY_DEFAULT_MERCHANT_CATEGORY_CODE', 'Merchant'),
                'merchant_code'    => env('UPAY_DEFAULT_MERCHANT_CODE', 'TEST5'),
                'merchant_mobile'           => env('UPAY_DEFAULT_MERCHANT_MOBILE', '01912586163'),
                'transaction_currency_code' => 'BDT',
                'redirect_url'             => env('SHEBA_API_URL') . '/v2/payments/upay'
            ]
        ],
        'base_url'     => env('UPAY_BASE_URL', 'https://uat-pg.upay.systems')
    ],
    'aamarpay' => [
        'base_url' => env('AAMARPAY_BASE_URL'),
        'success_url' => env('SHEBA_API_URL') . '/v2/payments/aamarpay/success',
        'fail_url' => env('SHEBA_API_URL') . '/v2/payments/aamarpay/fail',
        'cancel_url' => env('SHEBA_API_URL') . '/v2/payments/aamarpay/cancel',
    ],
];
