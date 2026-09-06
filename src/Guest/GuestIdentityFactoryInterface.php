<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Auth\Session\Guest;

/**
 * Implementations should create instances of the identity interface for a guest non-authenticated user.
 */
interface GuestIdentityFactoryInterface
{
    /**
     * Creates an instance of identity interface for a guest non-authenticated user.
     *
     * @return GuestIdentityInterface Instance of identity interface for a guest non-authenticated.
     */
    public function create(): GuestIdentityInterface;
}
