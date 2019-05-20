<?php
//// 移动应用-蚂蚁习惯
//return [
//    'debug' => false,
//    'app_id' => 'wx55ed67be05c032ee',
//    'secret' => '59aa4653e94e39989fb0e738a224edc0',
//    'token' => '',
//    'aes_key' => '',
//
//    'log' => [
//        'level' => 'debug',
//        'permission' => 0777,
//        'file' => '/tmp/easywechat.log',
//    ],
//
//    'oauth' => [
//        'scopes' => ['snsapi_userinfo'],
//        'callback' => '/examples/oauth_callback.php',
//    ]
//];

// 移动应用-微选生活
return [
    'debug' => false,
    'app_id' => 'wx525e7a8b9f731ee0',
    'secret' => 'f95accbf57fa22a69fec4af644a3dd58',
    'token' => '',
    'aes_key' => '',

    'log' => [
        'level' => 'debug',
        'permission' => 0777,
        'file' => '/tmp/easywechat.log',
    ],

    'oauth' => [
        'scopes' => ['snsapi_userinfo'],
        'callback' => request()->url(true),
    ]
];