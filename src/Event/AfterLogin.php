<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Auth\Session\Event;

use Yiisoft\Auth\IdentityInterface;

final class AfterLogin
{
    public function __construct(private IdentityInterface $identity) {}

    public function getIdentity(): IdentityInterface
    {
        return $this->identity;
    }
}
