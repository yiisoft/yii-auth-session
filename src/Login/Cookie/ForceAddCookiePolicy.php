<?php

namespace Yiisoft\Yii\Auth\Session\Login\Cookie;

enum ForceAddCookiePolicy: string
{
    /**
     * Never adds the auto-login cookie.
     */
    case Never = 'Never';

    /**
     * Adds the auto-login cookie only after login.
     */
    case AfterLogin = 'AfterLogin';

    /**
     * Adds/renews the auto-login cookie during each authenticated request.
     */
    case RenewDuringRequest = 'RenewDuringRequest';
}
