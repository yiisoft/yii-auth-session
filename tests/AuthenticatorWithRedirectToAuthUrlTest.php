<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Auth\Session\Tests;

use HttpSoft\Message\Response;
use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use Yiisoft\Http\Status;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;
use Yiisoft\Yii\Auth\Session\Tests\Support\MockArraySessionStorage;
use Yiisoft\Yii\Auth\Session\Tests\Support\MockIdentity;
use Yiisoft\Yii\Auth\Session\Tests\Support\MockIdentityRepository;
use Yiisoft\Yii\Auth\Session\AuthManager;
use Yiisoft\Yii\Auth\Session\AuthenticatorWithRedirectToAuthUrl;

final class AuthenticatorWithRedirectToAuthUrlTest extends TestCase
{
    public function testSuccessfulAuthentication(): void
    {
        $authManager = $this->createAuthManager();
        $authManager->login(new MockIdentity('test-id'));
        $result = (new AuthenticatorWithRedirectToAuthUrl($authManager, new ResponseFactory()))->authenticate(new ServerRequest());

        $this->assertNotNull($result);
        $this->assertSame('test-id', $result->getId());
    }

    public function testIdentityNotAuthenticated(): void
    {
        $authManager = $this->createAuthManager();
        $result = (new AuthenticatorWithRedirectToAuthUrl($authManager, new ResponseFactory()))->authenticate(new ServerRequest());

        $this->assertNull($result);
    }

    public function testChallengeIsCorrect(): void
    {
        $response = new Response();
        $authManager = $this->createAuthManager();
        $challenge = (new AuthenticatorWithRedirectToAuthUrl($authManager, new ResponseFactory()))->challenge($response);

        $this->assertSame(Status::FOUND, $challenge->getStatusCode());
        $this->assertSame('/login', $challenge->getHeaderLine('Location'));
    }

    public function testCustomAuthUrl(): void
    {
        $response = new Response();
        $authManager = $this->createAuthManager();
        $challenge = (new AuthenticatorWithRedirectToAuthUrl($authManager, new ResponseFactory()))
            ->withAuthUrl('/custom-auth-url')
            ->challenge($response);

        $this->assertSame('/custom-auth-url', $challenge->getHeaderLine('Location'));
    }

    public function testImmutability(): void
    {
        $original = new AuthenticatorWithRedirectToAuthUrl($this->createAuthManager(), new ResponseFactory());

        $this->assertNotSame($original, $original->withAuthUrl('/custom-auth-url'));
    }

    private function createAuthManager(): AuthManager
    {
        return new AuthManager(new MockArraySessionStorage(), new MockIdentityRepository(), new SimpleEventDispatcher());
    }
}
