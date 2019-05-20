<?php
// 极限挑战团 xiguanzaoqi
return [
    'debug' => false,
    'app_id' => 'wx3bf9734884a5c247',
    'secret' => 'e57d43f78a708b6eeb70d9a20173cd66',
    'token' => 'LHMPWX',
    'aes_key' => 'U3yDWUdiLxhpD2D06foeGHzdUb2fod1GrpLmz94Tz6w',

    'log' => [
        'level' => 'debug',
        'permission' => 0777,
        'file' => '/tmp/easywechat.log',
    ],

    'oauth' => [
        'scopes' => ['snsapi_userinfo'],
        'callback' => request()->url(true),
    ],

    'payment' => [
        'merchant_id' => '1465341202',
        'key' => '83c2c0f1f1518e510ee90bd4884638f3',
        'cert_path' => __DIR__ . '/cert/apiclient_cert_xiguanzaoqi.pem',
        'key_path' => __DIR__ . '/cert/apiclient_key_xiguanzaoqi.pem',
        'notify_url' => config('site.url') . '/home/payment/notify_wx'
    ],
];
