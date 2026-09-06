<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Auth\Session\Tests\Login;

use HttpSoft\Message\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use stdClass;
use Yiisoft\Auth\Middleware\Authentication;
use Yiisoft\Yii\Auth\Session\Tests\Support\MockArraySessionStorage;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;
use Yiisoft\Yii\Auth\Session\AuthManager;
use Yiisoft\Yii\Auth\Session\Event\AfterLogin;
use Yiisoft\Yii\Auth\Session\Event\BeforeLogin;
use Yiisoft\Yii\Auth\Session\Guest\GuestIdentity;
use Yiisoft\Yii\Auth\Session\Login\LoginMiddleware;
use Yiisoft\Yii\Auth\Session\Tests\Support\LastMessageLogger;
use Yiisoft\Yii\Auth\Session\Tests\Support\MockIdentity;
use Yiisoft\Yii\Auth\Session\Tests\Support\MockIdentityRepository;

final class LoginMiddlewareTest extends TestCase
{
    private const IDENTITY_ID = 'test-id';

    private MockIdentity $identity;
    private LastMessageLogger $logger;
    private SimpleEventDispatcher $eventDispatcher;
    private AuthManager $AuthManager;

    protected function setUp(): void
    {
        $this->identity = new MockIdentity(self::IDENTITY_ID);
        $this->logger = new LastMessageLogger();
        $this->eventDispatcher = new SimpleEventDispatcher();
        $this->AuthManager = new AuthManager(new MockArraySessionStorage(), new MockIdentityRepository(), $this->eventDispatcher);
    }

    public function testCorrectLogin(): void
    {
        $middleware = new LoginMiddleware($this->AuthManager, $this->logger);

        $middleware->process($this->createServerRequest(), $this->createRequestHandler());

        $this->assertNull($this->logger->getLastMessage());
        $this->assertInstanceOf(MockIdentity::class, $this->AuthManager->getIdentity());
        $this->assertSame(self::IDENTITY_ID, $this->AuthManager
            ->getIdentity()
            ->getId());
    }

    public function testCorrectProcessWithNonGuestUser(): void
    {
        $AuthManager = new AuthManager(new MockArraySessionStorage(), new MockIdentityRepository($this->identity), $this->eventDispatcher);
        $middleware = new LoginMiddleware($AuthManager, $this->logger);

        $middleware->process($this->createServerRequest(), $this->createRequestHandler());

        $this->assertNull($this->logger->getLastMessage());
        $this->assertInstanceOf(MockIdentity::class, $AuthManager->getIdentity());
        $this->assertSame(self::IDENTITY_ID, $AuthManager
            ->getIdentity()
            ->getId());
        $this->assertCount(2, $this->eventDispatcher->getEvents());
        $this->assertSame([BeforeLogin::class, AfterLogin::class], $this->eventDispatcher->getEventClasses());

        $middleware->process($this->createServerRequest(), $this->createRequestHandler());

        $this->assertNull($this->logger->getLastMessage());
        $this->assertInstanceOf(MockIdentity::class, $AuthManager->getIdentity());
        $this->assertSame(self::IDENTITY_ID, $AuthManager
            ->getIdentity()
            ->getId());
        $this->assertCount(2, $this->eventDispatcher->getEvents());
        $this->assertSame([BeforeLogin::class, AfterLogin::class], $this->eventDispatcher->getEventClasses());
    }

    public function testIdentityNotFound(): void
    {
        $middleware = new LoginMiddleware($this->AuthManager, $this->logger);

        $middleware->process($this->createServerRequest(false), $this->createRequestHandler());

        $this->assertInstanceOf(GuestIdentity::class, $this->AuthManager->getIdentity());
        $this->assertNull($this->AuthManager->getIdentity()->getId());
    }

    public static function dataUnableToAuthenticateUserDebugMessage(): iterable
    {
        yield ['true', true];
        yield ['false', false];
        yield ['"123"', 123];
        yield ['"10.4"', 10.4];
        yield ['"hello"', 'hello'];
        yield ['of type null', null];
        yield ['of type stdClass', new stdClass()];
        yield ['of type array', ['a', 'b']];
    }

    #[DataProvider('dataUnableToAuthenticateUserDebugMessage')]
    public function testUnableToAuthenticateUserDebugMessage(string $expectedType, mixed $identity): void
    {
        $middleware = new LoginMiddleware($this->AuthManager, $this->logger);

        $middleware->process(
            (new ServerRequest())->withAttribute(Authentication::class, $identity),
            $this->createRequestHandler(),
        );

        $this->assertSame(
            'Unable to authenticate user by token ' . $expectedType . '. Identity not found.',
            $this->logger->getLastMessage(),
        );
    }

    private function createServerRequest(bool $withIdentity = true): ServerRequestInterface
    {
        return (new ServerRequest())->withAttribute(Authentication::class, $withIdentity ? $this->identity : null);
    }

    private function createRequestHandler(): RequestHandlerInterface
    {
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $requestHandler
            ->expects($this->once())
            ->method('handle')
        ;

        return $requestHandler;
    }
}
