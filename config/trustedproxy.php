<?php

return [
    // 'proxies' => ['172.18.0.1'],
    'proxies' => '*',
    // 'proxies' => '**',

    'headers' => [
        (defined('Illuminate\Http\Request::HEADER_FORWARDED') ? Illuminate\Http\Request::HEADER_FORWARDED : 'forwarded') => null,
        \Illuminate\Http\Request::HEADER_CLIENT_IP => 'X_FORWARDED_FOR',
        \Illuminate\Http\Request::HEADER_CLIENT_HOST => null,
        \Illuminate\Http\Request::HEADER_CLIENT_PROTO => 'X_FORWARDED_PROTO',
        \Illuminate\Http\Request::HEADER_CLIENT_PORT => 'X_FORWARDED_PORT'
    ]
];
