<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Auth\Session\Tests;

use HttpSoft\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;
use Yiisoft\Yii\Auth\Session\Tests\Support\MockArraySessionStorage;
use Yiisoft\Yii\Auth\Session\Tests\Support\MockIdentity;
use Yiisoft\Yii\Auth\Session\Tests\Support\MockIdentityRepository;
use Yiisoft\Yii\Auth\Session\AuthManager;
use Yiisoft\Yii\Auth\Session\Authenticator;

final class AuthenticatorTest extends TestCase
{
    public function testSuccessfulAuthentication(): void
    {
        $authManager = $this->createAuthManager();
        $authManager->login(new MockIdentity('test-id'));
        $result = (new Authenticator($authManager))->authenticate(new ServerRequest());

        $this->assertNotNull($result);
        $this->assertSame('test-id', $result->getId());
    }

    public function testIdentityNotAuthenticated(): void
    {
        $user = $this->createAuthManager();
        $result = (new Authenticator($user))->authenticate(new ServerRequest());

        $this->assertNull($result);
    }

    private function createAuthManager(): AuthManager
    {
        return new AuthManager(new MockArraySessionStorage(), new MockIdentityRepository(), new SimpleEventDispatcher());
    }
}
