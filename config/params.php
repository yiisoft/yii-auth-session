<?php

declare(strict_types=1);

return [
    'yiisoft/yii-auth-session' => [
        'authUrl' => '/login',
        'cookieLogin' => [
            'forceAddCookie' => false,
            'duration' => 'P5D', // 5 days, see format on https://www.php.net/manual/dateinterval.construct.php
            'cookieSecure' => false, // whether the client should send back the cookie only over HTTPS connection
            'encryptorKey' => '', // secret key to encrypt the auto-login cookie value, set it to `null` to store it unencrypted
            'signerKey' => '' // secret key to sign the auto-login cookie value, set it to `null` to store it unsigned
        ],
    ],
];
