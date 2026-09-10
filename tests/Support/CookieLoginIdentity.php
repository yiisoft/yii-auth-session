<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Auth\Session\Tests\Support;

use Yiisoft\Yii\Auth\Session\Login\Cookie\CookieLoginIdentityInterface;

final class CookieLoginIdentity implements CookieLoginIdentityInterface
{
    public const ID = '42';
    public const KEY_CORRECT = 'auto-login-key-correct';
    public const KEY_INCORRECT = 'auto-login-key-incorrect';

    public function getCookieLoginKey(): string
    {
        return self::KEY_CORRECT;
    }

    public function validateCookieLoginKey(string $key): bool
    {
        return $key === $this->getCookieLoginKey();
    }

    public function getId(): ?string
    {
        return self::ID;
    }
}
