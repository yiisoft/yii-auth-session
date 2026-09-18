<?php

declare(strict_types=1);

use Yiisoft\Auth\AuthenticatorInterface;
use Yiisoft\Auth\AuthenticatorWithChallengeInterface;
use Yiisoft\Yii\Auth\Session\AuthManager;
use Yiisoft\Yii\Auth\Session\AuthenticatorWithRedirectToAuthUrl;
use Yiisoft\Yii\Auth\Session\Guest\GuestIdentityFactory;
use Yiisoft\Yii\Auth\Session\Guest\GuestIdentityFactoryInterface;
use Yiisoft\Yii\Auth\Session\Login\Cookie\CookieLogin;
use Yiisoft\Yii\Auth\Session\Login\Cookie\CookieLoginMiddleware;

/** @var array $params */

return [
    AuthManager::class => [
        'reset' => function () {
            $this->clear();
        },
    ],

    AuthenticatorWithRedirectToAuthUrl::class => [
        'withAuthUrl()' => [$params['yiisoft/yii-auth-session']['authUrl']],
    ],

    AuthenticatorInterface::class => AuthenticatorWithRedirectToAuthUrl::class,
    AuthenticatorWithChallengeInterface::class => AuthenticatorWithRedirectToAuthUrl::class,
    GuestIdentityFactoryInterface::class => GuestIdentityFactory::class,

    CookieLoginMiddleware::class => [
        '__construct()' => [
            'forceAddCookiePolicy' => $params['yiisoft/yii-auth-session']['cookieLogin']['forceAddCookiePolicy']
        ],
    ],

    CookieLogin::class => [
        '__construct()' => [
            'duration' => $params['yiisoft/yii-auth-session']['cookieLogin']['duration'] !== null ?
                new DateInterval($params['yiisoft/yii-auth-session']['cookieLogin']['duration']) :
                null,
            'cookieSecure' => $params['yiisoft/yii-auth-session']['cookieLogin']['cookieSecure'],
            'encryptorKey' => $params['yiisoft/yii-auth-session']['cookieLogin']['encryptorKey'],
            'signerKey' => $params['yiisoft/yii-auth-session']['cookieLogin']['signerKey']
        ],
    ]
];
