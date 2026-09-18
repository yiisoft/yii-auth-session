<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Auth\Session\Tests\Login\Cookie;

use DateInterval;
use HttpSoft\Message\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;
use Yiisoft\Yii\Auth\Session\Event\AfterLogin;
use Yiisoft\Yii\Auth\Session\Event\BeforeLogin;
use Yiisoft\Yii\Auth\Session\Login\Cookie\CookieLogin;
use Yiisoft\Yii\Auth\Session\Login\Cookie\CookieLoginMiddleware;
use Yiisoft\Yii\Auth\Session\Login\Cookie\ForceAddCookiePolicy;
use Yiisoft\Yii\Auth\Session\Tests\Support\MockArraySessionStorage;
use Yiisoft\Yii\Auth\Session\Tests\Support\MockIdentityRepository;
use Yiisoft\Yii\Auth\Session\Tests\Support\CookieLoginIdentity;
use Yiisoft\Yii\Auth\Session\Tests\Support\LastMessageLogger;
use Yiisoft\Yii\Auth\Session\AuthManager;
use Yiisoft\Cookies\Cookie;

use function json_encode;
use function time;

use const JSON_THROW_ON_ERROR;

final class CookieLoginMiddlewareTest extends TestCase
{
    private LastMessageLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new LastMessageLogger();
    }

    public function testCorrectLogin(): void
    {
        $AuthManager = $this->createAuthManager();

        $middleware = new CookieLoginMiddleware(
            $AuthManager,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $middleware->process($this->getRequestWithAutoLoginCookie(), $this->getRequestHandler());

        $this->assertNull($this->getLastLogMessage());
        $this->assertSame(CookieLoginIdentity::ID, $AuthManager
            ->getIdentity()
            ->getId());
    }

    public function testSessionCookieLogin(): void
    {
        $AuthManager = $this->createAuthManager();

        $middleware = new CookieLoginMiddleware(
            $AuthManager,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $middleware->process($this->getRequestWithAutoLoginCookie(expires: 0), $this->getRequestHandler());

        $this->assertNull($this->getLastLogMessage());
        $this->assertSame(CookieLoginIdentity::ID, $AuthManager
            ->getIdentity()
            ->getId());
    }

    public function testCorrectProcessWithNonGuestUser(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $AuthManager = new AuthManager(
            $this->createSession(),
            $this->createIdentityRepository(),
            $eventDispatcher,
        );

        $middleware = new CookieLoginMiddleware(
            $AuthManager,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $request = $this->getRequestWithAutoLoginCookie();

        $middleware->process($request, $this->getRequestHandler());

        $this->assertNull($this->getLastLogMessage());
        $this->assertSame(CookieLoginIdentity::ID, $AuthManager
            ->getIdentity()
            ->getId());
        $this->assertCount(2, $eventDispatcher->getEvents());
        $this->assertSame([BeforeLogin::class, AfterLogin::class], $eventDispatcher->getEventClasses());

        $middleware->process($request, $this->getRequestHandler());

        $this->assertNull($this->getLastLogMessage());
        $this->assertSame(CookieLoginIdentity::ID, $AuthManager
            ->getIdentity()
            ->getId());
        $this->assertCount(2, $eventDispatcher->getEvents());
        $this->assertSame([BeforeLogin::class, AfterLogin::class], $eventDispatcher->getEventClasses());
    }

    public function testInvalidKey(): void
    {
        $middleware = new CookieLoginMiddleware(
            $this->createAuthManager(),
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $request = $this->getRequestWithAutoLoginCookie(CookieLoginIdentity::KEY_INCORRECT);

        $response = $middleware->process($request, $this->getRequestHandler());

        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
        $this->assertSame('Unable to authenticate user by cookie. Invalid key.', $this->getLastLogMessage());
    }

    public function testInvalidExpires(): void
    {
        $middleware = new CookieLoginMiddleware(
            $this->createAuthManager(),
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $request = $this->getRequestWithAutoLoginCookie(CookieLoginIdentity::KEY_CORRECT, time() - 1);

        $response = $middleware->process($request, $this->getRequestHandler());

        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
        $this->assertSame('Unable to authenticate user by cookie. Lifetime has expired.', $this->getLastLogMessage());
    }

    public function testNoCookie(): void
    {
        $middleware = new CookieLoginMiddleware(
            $this->createAuthManager(),
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $request = $this->getRequestWithCookies([]);

        $response = $middleware->process($request, $this->getRequestHandler());

        $this->assertNull($this->getLastLogMessage());
        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
    }

    public function testEmptyCookie(): void
    {
        $middleware = new CookieLoginMiddleware(
            $this->createAuthManager(),
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $request = $this->getRequestWithCookies(['autoLogin' => '']);

        $response = $middleware->process($request, $this->getRequestHandler());

        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
        $this->assertSame('Unable to authenticate user by cookie. Invalid cookie.', $this->getLastLogMessage());
    }

    public function testInvalidCookie(): void
    {
        $middleware = new CookieLoginMiddleware(
            $this->createAuthManager(),
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $request = $this->getRequestWithCookies([
            'autoLogin' => json_encode([
                CookieLoginIdentity::ID,
                CookieLoginIdentity::KEY_CORRECT,
                time() + 3600,
                'weird stuff',
            ], JSON_THROW_ON_ERROR),
        ]);

        $response = $middleware->process($request, $this->getRequestHandler());

        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
        $this->assertSame('Unable to authenticate user by cookie. Invalid cookie.', $this->getLastLogMessage());
    }

    public function testCorrectLoginWithSignedCookie(): void
    {
        $authManager = $this->createAuthManager();

        $cookieLogin = new CookieLogin(signerKey: 'secret-key');
        $middleware = new CookieLoginMiddleware(
            $authManager,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin
        );

        $cookieValue = json_encode(
            [CookieLoginIdentity::ID, CookieLoginIdentity::KEY_CORRECT, 0],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $middleware->process(
            $this->getRequestWithCookies([
                'autoLogin' => $cookieLogin->signer->sign(new Cookie(name: $cookieLogin->getCookieName(), value: $cookieValue))->getValue()
            ]),
            $this->getRequestHandler(),
        );

        $this->assertNull($this->getLastLogMessage());
        $this->assertSame(CookieLoginIdentity::ID, $authManager->getIdentity()->getId());
    }

    public function testCorrectLoginWithEncryptedCookie(): void
    {
        $authManager = $this->createAuthManager();

        $cookieLogin = new CookieLogin(encryptorKey: 'secret-key');
        $middleware = new CookieLoginMiddleware(
            $authManager,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin
        );

        $cookieValue = json_encode(
            [CookieLoginIdentity::ID, CookieLoginIdentity::KEY_CORRECT, 0],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $middleware->process(
            $this->getRequestWithCookies([
                'autoLogin' => $cookieLogin->encryptor->encrypt(new Cookie(name: $cookieLogin->getCookieName(), value: $cookieValue))->getValue()
            ]),
            $this->getRequestHandler(),
        );

        $this->assertNull($this->getLastLogMessage());
        $this->assertSame(CookieLoginIdentity::ID, $authManager->getIdentity()->getId());
    }

    public function testCorrectLoginWithSignedAndEncryptedCookie(): void
    {
        $authManager = $this->createAuthManager();

        $cookieLogin = new CookieLogin(encryptorKey: 'secret-key1', signerKey: 'secret-key2');
        $middleware = new CookieLoginMiddleware(
            $authManager,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin
        );

        $cookieValue = json_encode(
            [CookieLoginIdentity::ID, CookieLoginIdentity::KEY_CORRECT, 0],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $middleware->process(
            $this->getRequestWithCookies([
                'autoLogin' => $cookieLogin->encryptor->encrypt($cookieLogin->signer->sign(new Cookie(name: $cookieLogin->getCookieName(), value: $cookieValue)))->getValue()
            ]),
            $this->getRequestHandler(),
        );

        $this->assertNull($this->getLastLogMessage());
        $this->assertSame(CookieLoginIdentity::ID, $authManager->getIdentity()->getId());
    }

    public function testUnsignedCookieIsRejectedWhenSignatureKeyIsSet(): void
    {
        $authManager = $this->createAuthManager();

        $cookieLogin = new CookieLogin(signerKey: 'secret-key');
        $middleware = new CookieLoginMiddleware(
            $authManager,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin
        );

        $cookieValue = json_encode(
            [CookieLoginIdentity::ID, CookieLoginIdentity::KEY_CORRECT, 0],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $middleware->process(
            $this->getRequestWithCookies([
                'autoLogin' => $cookieValue
            ]),
            $this->getRequestHandler(),
        );

        $this->assertSame('The "autoLogin" cookie value is not validly signed.', $this->getLastLogMessage());
        $this->assertSame(false, $authManager->isAuthenticated());
    }

    public function testUnsignedCookieIsRejectedWhenEncryptedKeyIsSet(): void
    {
        $authManager = $this->createAuthManager();

        $cookieLogin = new CookieLogin(encryptorKey: 'secret-key');
        $middleware = new CookieLoginMiddleware(
            $authManager,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin
        );

        $cookieValue = json_encode(
            [CookieLoginIdentity::ID, CookieLoginIdentity::KEY_CORRECT, 0],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $middleware->process(
            $this->getRequestWithCookies([
                'autoLogin' => $cookieValue
            ]),
            $this->getRequestHandler(),
        );

        $this->assertSame('The "autoLogin" cookie value is not validly encrypted.', $this->getLastLogMessage());
        $this->assertSame(false, $authManager->isAuthenticated());
    }

    public function testUnsignedCookieIsRejectedWhenSignatureAndEncryptedKeyIsSet(): void
    {
        $authManager = $this->createAuthManager();

        $cookieLogin = new CookieLogin(encryptorKey: 'secret-key1', signerKey: 'secret-key2');
        $middleware = new CookieLoginMiddleware(
            $authManager,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $cookieLogin
        );

        $cookieValue = json_encode(
            [CookieLoginIdentity::ID, CookieLoginIdentity::KEY_CORRECT, 0],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $middleware->process(
            $this->getRequestWithCookies([
                'autoLogin' => $cookieValue
            ]),
            $this->getRequestHandler(),
        );

        $this->assertSame('The "autoLogin" cookie value is not validly encrypted.', $this->getLastLogMessage());
        $this->assertSame(false, $authManager->isAuthenticated());
    }

    public function testIncorrectIdentity(): void
    {
        $middleware = new CookieLoginMiddleware(
            $this->createAuthManager(),
            $this->getIncorrectIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Identity repository must return an instance of Yiisoft\\Yii\\Auth\\Session\\Login\\Cookie\\CookieLoginIdentityInterface'
            . ' in order for auto-login to function.',
        );

        $middleware->process($this->getRequestWithAutoLoginCookie(), $this->getRequestHandlerThatIsNotCalled());
    }

    public function testIdentityNotFound(): void
    {
        $middleware = new CookieLoginMiddleware(
            $this->createAuthManager(),
            $this->getEmptyIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $response = $middleware->process($this->getRequestWithAutoLoginCookie(), $this->getRequestHandler());

        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
        $this->assertSame(
            'Unable to authenticate user by cookie. Identity "' . CookieLoginIdentity::ID . '" not found.',
            $this->getLastLogMessage(),
        );
    }

    public function testForceAddCookiePolicyNeverAfterLoginAndWithoutAutoLoginCookie(): void
    {
        $AuthManager = $this->createAuthManager();

        $middleware = new CookieLoginMiddleware(
            $AuthManager,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
            ForceAddCookiePolicy::Never,
        );

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(static function () use ($AuthManager) {
                $AuthManager->login(new CookieLoginIdentity());
                return new Response();
            });

        $response = $middleware->process($this->getRequestWithCookies([]), $handler);

        $this->assertNull($this->getLastLogMessage());
        $this->assertTrue($AuthManager->isAuthenticated());

        $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
    }

    public function testForceAddCookiePolicyAfterLoginAfterLoginAndWithoutAutoLoginCookie(): void
    {
        $AuthManager = $this->createAuthManager();

        $middleware = new CookieLoginMiddleware(
            $AuthManager,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
            ForceAddCookiePolicy::AfterLogin,
        );

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(static function () use ($AuthManager) {
                $AuthManager->login(new CookieLoginIdentity());
                return new Response();
            });

        $response = $middleware->process($this->getRequestWithCookies([]), $handler);

        $this->assertNull($this->getLastLogMessage());
        $this->assertTrue($AuthManager->isAuthenticated());

        $this->assertMatchesRegularExpression(
            '#autoLogin=%5B%2242%22%2C%22auto-login-key-correct%22%2C[0-9]{10}%5D;'
            . ' Expires=.*?; Max-Age=604800; Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public static function forceAddCookiePolicyDataProvider(): array
    {
        return [
            'Never' => [ForceAddCookiePolicy::Never],
            'AfterLogin' => [ForceAddCookiePolicy::AfterLogin],
            'RenewDuringRequest' => [ForceAddCookiePolicy::RenewDuringRequest],
        ];
    }

    #[DataProvider('forceAddCookiePolicyDataProvider')]
    public function testForceAddCookiePolicyWithAutoLoginCookie(ForceAddCookiePolicy $forceAddCookiePolicy): void
    {
        $AuthManager = $this->createAuthManager();

        $middleware = new CookieLoginMiddleware(
            $AuthManager,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
            $forceAddCookiePolicy
        );

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->willReturn(new Response());
        $response = $middleware->process($this->getRequestWithAutoLoginCookie(), $handler);

        $this->assertNull($this->getLastLogMessage());
        $this->assertTrue($AuthManager->isAuthenticated());

        if ($forceAddCookiePolicy === ForceAddCookiePolicy::RenewDuringRequest) {
            $this->assertMatchesRegularExpression(
                '#autoLogin=%5B%2242%22%2C%22auto-login-key-correct%22%2C[0-9]{10}%5D;'
                . ' Expires=.*?; Max-Age=604800; Path=/; Secure; HttpOnly; SameSite=Lax#',
                $response->getHeaderLine('Set-Cookie'),
            );
        } else {
            $this->assertEmpty($response->getHeaderLine('Set-Cookie'));
        }
    }

    public function testRemoveCookieAfterLogout(): void
    {
        $AuthManager = $this->createAuthManager();

        $middleware = new CookieLoginMiddleware(
            $AuthManager,
            $this->getCookieLoginIdentityRepository(),
            $this->logger,
            $this->createCookieLogin(),
        );

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler
            ->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function () use ($AuthManager) {
                $AuthManager->logout();
                return new Response();
            });

        $response = $middleware->process($this->getRequestWithAutoLoginCookie(), $handler);

        $this->assertNull($this->getLastLogMessage());
        $this->assertMatchesRegularExpression(
            '#autoLogin=; Expires=.*?; Max-Age=-\d++; Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    private function getRequestHandler(): RequestHandlerInterface
    {
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $requestHandler
            ->expects($this->once())
            ->method('handle');

        return $requestHandler;
    }

    private function getRequestHandlerThatIsNotCalled(): RequestHandlerInterface
    {
        $requestHandler = $this->createMock(RequestHandlerInterface::class);

        $requestHandler
            ->expects($this->never())
            ->method('handle');

        return $requestHandler;
    }

    private function getIncorrectIdentityRepository(): IdentityRepositoryInterface
    {
        return $this->getIdentityRepository($this->createMock(IdentityInterface::class));
    }

    private function getCookieLoginIdentityRepository(): IdentityRepositoryInterface
    {
        return $this->getIdentityRepository(new CookieLoginIdentity());
    }

    private function getEmptyIdentityRepository(): IdentityRepositoryInterface
    {
        return $this->createMock(IdentityRepositoryInterface::class);
    }

    private function getIdentityRepository(IdentityInterface $identity): IdentityRepositoryInterface
    {
        $identityRepository = $this->createMock(IdentityRepositoryInterface::class);

        $identityRepository
            ->expects($this->any())
            ->method('findIdentity')
            ->willReturn($identity);

        return $identityRepository;
    }

    private function getRequestWithAutoLoginCookie(
        string $authKey = CookieLoginIdentity::KEY_CORRECT,
        ?int   $expires = null,
    ): ServerRequestInterface
    {
        return $this->getRequestWithCookies([
            'autoLogin' => json_encode([CookieLoginIdentity::ID, $authKey, $expires ?? time() + 3600], JSON_THROW_ON_ERROR),
        ]);
    }

    private function getRequestWithCookies(array $cookies): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $request
            ->expects($this->any())
            ->method('getCookieParams')
            ->willReturn($cookies);

        return $request;
    }

    private function createCookieLogin(): CookieLogin
    {
        return new CookieLogin(new DateInterval('P1W'));
    }

    private function createAuthManager(): AuthManager
    {
        return new AuthManager($this->createSession(), $this->createIdentityRepository(), $this->createEventDispatcher());
    }

    private function createSession(): MockArraySessionStorage
    {
        return new MockArraySessionStorage();
    }

    private function createIdentityRepository(): IdentityRepositoryInterface
    {
        return new MockIdentityRepository();
    }

    private function createEventDispatcher(): SimpleEventDispatcher
    {
        return new SimpleEventDispatcher();
    }

    private function getLastLogMessage(): ?string
    {
        return $this->logger->getLastMessage();
    }
}
