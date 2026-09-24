<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MyNet Back API
    |--------------------------------------------------------------------------
    |
    | mynet_back 后端 API 基础 URL，用于配置包下载功能。
    | CTL 通过该地址代理请求获取节点配置、密钥等数据。
    |
    */
    'mynet_back' => [
        'api_url' => env('MYNET_BACK_API_URL', 'https://api.mynet.club'),
        'timeout' => (int) env('MYNET_BACK_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | MyNet 站点 URL
    |--------------------------------------------------------------------------
    |
    | 各站点的 URL，用于 Blade 视图链接、配置包输出等。
    | 本地开发在 .env 中覆盖为 *.mynet.local。
    |
    */
    'mynet_urls' => [
        'www' => env('MYNET_WWW_URL', 'https://www.mynet.club'),
        'gui' => env('MYNET_GUI_URL', 'https://my.mynet.club'),
        'docs' => env('MYNET_DOCS_URL', 'https://www.mynet.club/docs'),
    ],

];
