<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Auth\Session\Tests;

use HttpSoft\Message\ResponseFactory;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use ReflectionObject;
use Yiisoft\Auth\AuthenticatorInterface;
use Yiisoft\Auth\AuthenticatorWithChallengeInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Yii\Auth\Session\AuthenticatorWithRedirectToAuthUrl;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;
use Yiisoft\Test\Support\Log\SimpleLogger;
use Yiisoft\Yii\Auth\Session\AuthManager;
use Yiisoft\Yii\Auth\Session\Guest\GuestIdentityFactory;
use Yiisoft\Yii\Auth\Session\Guest\GuestIdentityFactoryInterface;
use Yiisoft\Yii\Auth\Session\Login\Cookie\CookieLogin;
use Yiisoft\Yii\Auth\Session\Login\Cookie\CookieLoginMiddleware;
use Yiisoft\Yii\Auth\Session\Login\LoginMiddleware;
use Yiisoft\Yii\Auth\Session\Tests\Support\MockIdentityRepository;
use Yiisoft\Di\BuildingException;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Session\Session;

use function dirname;

final class ConfigTest extends TestCase
{
    public function testBase(): void
    {
        $container = $this->createContainer();

        $this->assertInstanceOf(AuthManager::class, $container->get(AuthManager::class));
        $this->assertInstanceOf(GuestIdentityFactory::class, $container->get(GuestIdentityFactoryInterface::class));
        $this->assertInstanceOf(LoginMiddleware::class, $container->get(LoginMiddleware::class));

        $authenticator = $container->get(AuthenticatorInterface::class);
        $this->assertInstanceOf(AuthenticatorWithRedirectToAuthUrl::class, $authenticator);
        $this->assertSame('/login', $this->getInaccessibleProperty($authenticator, 'authUrl'));

        $authenticatorWithChallenge = $container->get(AuthenticatorWithChallengeInterface::class);
        $this->assertInstanceOf(AuthenticatorWithRedirectToAuthUrl::class, $authenticatorWithChallenge);

        $this->expectException(BuildingException::class);
        $this->expectExceptionMessage("The 'encryptorKey' argument must be set through the 'yiisoft/yii-auth-session' => ['cookieLogin' => ['encryptorKey' => 'your secret key']] in config/web/params.php.");
        $cookieLogin = $container->get(CookieLogin::class);

        $this->expectException(BuildingException::class);
        $this->expectExceptionMessage("The 'encryptorKey' argument must be set through the 'yiisoft/yii-auth-session' => ['cookieLogin' => ['encryptorKey' => 'your secret key']] in config/web/params.php.");
        $cookieLoginMiddleware = $container->get(CookieLoginMiddleware::class);
    }

    public function testOverrideParams(): void
    {
        $container = $this->createContainer([
            'yiisoft/yii-auth-session' => [
                'authUrl' => '/override',
                'cookieLogin' => [
                    'forceAddCookie' => true,
                    'duration' => 'P2D',
                    'cookieSecure' => true,
                    'encryptorKey' => null,
                    'signerKey' => null
                ],
            ],
        ]);

        $authenticatorWithLoginChalleng = $container->get(AuthenticatorWithRedirectToAuthUrl::class);

        $this->assertSame('/override', $this->getInaccessibleProperty($authenticatorWithLoginChalleng, 'authUrl'));

        $cookieLogin = $container->get(CookieLogin::class);

        $this->assertInstanceOf(CookieLogin::class, $cookieLogin);
        $this->assertSame(2, $this
            ->getInaccessibleProperty($cookieLogin, 'duration')
            ->d);

        $cookieLoginMiddleware = $container->get(CookieLoginMiddleware::class);

        $this->assertInstanceOf(CookieLoginMiddleware::class, $cookieLoginMiddleware);
        $this->assertTrue($this->getInaccessibleProperty($cookieLoginMiddleware, 'forceAddCookie'));
    }

    private function createContainer(?array $params = null): Container
    {
        return new Container(
            ContainerConfig::create()->withDefinitions(
                $this->getDiConfig($params)
                + [
                    EventDispatcherInterface::class => SimpleEventDispatcher::class,
                    IdentityRepositoryInterface::class => MockIdentityRepository::class,
                    ResponseFactoryInterface::class => ResponseFactory::class,
                    LoggerInterface::class => SimpleLogger::class,
                    SessionInterface::class => Session::class,
                ],
            ),
        );
    }

    private function getDiConfig(?array $params = null): array
    {
        $params ??= $this->getParams();
        return require dirname(__DIR__) . '/config/di-web.php';
    }

    private function getParams(): array
    {
        return require dirname(__DIR__) . '/config/params.php';
    }

    private function getInaccessibleProperty(object $object, string $propertyName)
    {
        $class = new ReflectionObject($object);
        $property = $class->getProperty($propertyName);
        return $property->getValue($object);
    }
}
