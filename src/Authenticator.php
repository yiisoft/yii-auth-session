<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Auth\Session;

use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Auth\AuthenticatorInterface;
use Yiisoft\Auth\IdentityInterface;

/**
 * Implementation of the `AuthenticatorInterface` for authenticating users in the web applications.
 */
class Authenticator implements AuthenticatorInterface
{
    public function __construct(
        private readonly AuthManager $authManager
    ) {}

    public function authenticate(ServerRequestInterface $request): ?IdentityInterface
    {
        if ($this->authManager->isAuthenticated()) {
            return $this->authManager->getIdentity();
        }

        return null;
    }
}
