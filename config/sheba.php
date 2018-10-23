<?php

return [
    'front_url' => env('SHEBA_FRONT_END_URL'),
    'admin_url' => env('SHEBA_BACKEND_URL'),
    'api_url' => env('SHEBA_API_URL'),
    's3_url' => env('S3_URL'),
    'socket_url' => env('SHEBA_SOCKET_URL'),
    'socket_on' => env('SHEBA_SOCKET_ON', true),
    'send_push_notifications' => env('SHEBA_SEND_PUSH_NOTIFICATIONS', true),
    'partners_url' => env('SHEBA_PARTNER_END_URL'),
    'db_backup' => env('SHEBA_DB_BACKUP', false),
    'portal' => 'customer-portal',
    //'revision' => file_get_contents(base_path()."/revision"),
    'order_code_start' => 8000,
    'job_code_start' => 16000,
    'portals' => [
        'admin-portal', 'partner-portal', 'manager-app', 'customer-app', 'customer-portal', 'resource-portal', 'resource-app', 'bondhu-app', 'automatic'
    ],
    'push_notification_topic_name' => [
        'customer'  => env('CUSTOMER_TOPIC_NAME', 'customer_'),
        'resource'  => env('RESOURCE_TOPIC_NAME', 'resource_'),
        'manager'   => env('MANAGER_TOPIC_NAME', 'manager_')
    ],
    'push_notification_channel_name' => [
        'customer'  => 'customer_channel',
        'manager'   => 'manager_channel',
        'resource'  => 'resource_channel'
    ],
    'push_notification_sound' => [
        'customer'  => 'default',
        'manager'   => 'notification_sound'
    ]
];