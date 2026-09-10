<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Auth\Session\Tests;

use BackedEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Access\AccessCheckerInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;
use Yiisoft\Yii\Auth\Session\AuthManager;
use Yiisoft\Yii\Auth\Session\Event\AfterLogin;
use Yiisoft\Yii\Auth\Session\Event\AfterLogout;
use Yiisoft\Yii\Auth\Session\Event\BeforeLogin;
use Yiisoft\Yii\Auth\Session\Event\BeforeLogout;
use Yiisoft\Yii\Auth\Session\Guest\GuestIdentity;
use Yiisoft\Yii\Auth\Session\Tests\Support\AccessCheckerStub;
use Yiisoft\Yii\Auth\Session\Tests\Support\Permission;
use Yiisoft\Yii\Auth\Session\Tests\Support\MockArraySessionStorage;
use Yiisoft\Yii\Auth\Session\Tests\Support\MockIdentity;
use Yiisoft\Yii\Auth\Session\Tests\Support\MockIdentityRepository;

final class AuthManagerTest extends TestCase
{
    public function testGetIdentityWithoutLogin(): void
    {
        $AuthManager = new AuthManager($this->createSession(), $this->createIdentityRepository(), $this->createEventDispatcher());

        $this->assertInstanceOf(GuestIdentity::class, $AuthManager->getIdentity());
    }

    public function testGetIdentityWithoutLoginAndSession(): void
    {
        $AuthManager = new AuthManager($this->createSession(), $this->createIdentityRepository(), $this->createEventDispatcher());

        $this->assertInstanceOf(GuestIdentity::class, $AuthManager->getIdentity());
    }

    public function testGetIdentityFromStorage(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withSession($this->createSession(['__auth_id' => $id]))
        ;

        $this->assertSame($identity, $AuthManager->getIdentity());
        $this->assertSame($id, $AuthManager->getId());
    }

    public function testGetIdentityIfSessionHasEqualAuthTimeout(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withSession($this->createSession(['__auth_id' => $id, '__auth_expire' => time()]))
            ->withAuthTimeout(60)
        ;

        $this->assertSame($identity, $AuthManager->getIdentity());
    }

    public function testGetIdentityIfSessionHasExpiredAuthTimeout(): void
    {
        $session = $this->createSession([
            '__auth_id' => 'test-id',
            '__auth_expire' => strtotime('-1 day'),
        ]);

        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository(), $this->createEventDispatcher()))
            ->withAuthTimeout(60)
            ->withSession($session)
        ;

        $this->assertInstanceOf(GuestIdentity::class, $AuthManager->getIdentity());
        $this->assertFalse($session->has('__auth_id'));
        $this->assertFalse($session->has('__auth_expire'));
    }

    public function testGetIdentityIfSessionHasExpiredAbsoluteAuthTimeout(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $session = $this->createSession([
            '__auth_id' => $id,
            '__auth_absolute_expire' => strtotime('-1 day'),
        ]);

        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withAbsoluteAuthTimeout(60)
            ->withSession($session)
        ;

        $this->assertInstanceOf(GuestIdentity::class, $AuthManager->getIdentity());
    }

    public function testGetIdentityIfSessionHasEqualAbsoluteAuthTimeout(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $session = $this->createSession([
            '__auth_id' => $id,
            '__auth_absolute_expire' => time(),
        ]);

        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withAbsoluteAuthTimeout(60)
            ->withSession($session)
        ;

        $this->assertSame($identity, $AuthManager->getIdentity());
    }

    public function testGetIdentityAndSetAuthExpire(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $session = $this->createSession([
            '__auth_id' => $id,
        ]);

        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withAuthTimeout(60)
            ->withSession($session)
        ;

        $this->assertSame($identity, $AuthManager->getIdentity());
        $this->assertTrue($session->has('__auth_expire'));
    }

    public function testGetIdentityIfSessionHasExpiredAuthTimeoutAndWithoutId(): void
    {
        $session = $this->createSession([
            '__auth_expire' => strtotime('-1 day'),
        ]);

        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository(), $this->createEventDispatcher()))
            ->withAuthTimeout(60)
            ->withSession($session)
        ;

        $this->assertInstanceOf(GuestIdentity::class, $AuthManager->getIdentity());
        $this->assertTrue($session->has('__auth_expire'));
    }

    public function testGetOverriddenIdentity(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withSession($this->createSession(['__auth_id' => $id]))
        ;

        $overriddenIdentity = new MockIdentity('temp-id');
        $AuthManager->overrideIdentity($overriddenIdentity);

        $this->assertSame($overriddenIdentity, $AuthManager->getIdentity());
    }

    public function testLoginAndGetOverriddenIdentity(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository(), $this->createEventDispatcher()))
            ->withSession($this->createSession())
        ;

        $AuthManager->login($identity);
        $overriddenIdentity = new MockIdentity('temp-id');
        $AuthManager->overrideIdentity($overriddenIdentity);

        $this->assertSame($overriddenIdentity, $AuthManager->getIdentity());
    }

    public function testClearIdentityOverride(): void
    {
        $id = 'test-id';
        $identity = new MockIdentity($id);

        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withSession($this->createSession(['__auth_id' => $id]))
        ;

        $AuthManager->overrideIdentity(new MockIdentity('temp-id'));
        $AuthManager->clearIdentityOverride();

        $this->assertSame($identity, $AuthManager->getIdentity());
    }

    public function testClear(): void
    {
        $id = 'test-id';
        $identity = new class ($id) implements IdentityInterface {
            public function __construct(private ?string $id) {}

            public function getId(): ?string
            {
                $id = $this->id;
                $this->id = null;
                return $id;
            }
        };

        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withSession($this->createSession(['__auth_id' => $id]))
        ;

        $this->assertSame($id, $AuthManager->getId());

        $AuthManager->clear();

        $this->assertNull($AuthManager->getId());
    }

    public function testLogin(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository(), $eventDispatcher))
            ->withSession($this->createSession())
        ;

        $identity = $this->createIdentity('test-id');

        $this->assertTrue($AuthManager->login($identity));
        $this->assertSame($identity, $AuthManager->getIdentity());
        $this->assertCount(2, $eventDispatcher->getEvents());
        $this->assertSame([BeforeLogin::class, AfterLogin::class], $eventDispatcher->getEventClasses());
    }

    public function testLoginWithoutSession(): void
    {
        $eventDispatcher = $this->createEventDispatcher();
        $AuthManager = new AuthManager($this->createSession(), $this->createIdentityRepository(), $eventDispatcher);
        $identity = $this->createIdentity('test-id');

        $this->assertTrue($AuthManager->login($identity));
        $this->assertSame($identity, $AuthManager->getIdentity());
        $this->assertCount(2, $eventDispatcher->getEvents());
        $this->assertSame([BeforeLogin::class, AfterLogin::class], $eventDispatcher->getEventClasses());
    }

    public function testLoginAndSetAbsoluteAuthTimeout(): void
    {
        $id = 'test-id';
        $identity = $this->createIdentity($id);
        $session = $this->createSession();

        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository(), $this->createEventDispatcher()))
            ->withAbsoluteAuthTimeout(60)
            ->withSession($session)
        ;

        $AuthManager->login($identity);

        $this->assertSame($identity, $AuthManager->getIdentity());
        $this->assertSame($id, $session->get('__auth_id'));
        $this->assertTrue($session->has('__auth_absolute_expire'));

        // Second getting
        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withAbsoluteAuthTimeout(60)
            ->withSession($session)
        ;

        $this->assertSame($identity, $AuthManager->getIdentity());
    }

    public function testLoginAndSetAuthTimeout(): void
    {
        $id = 'test-id';
        $identity = $this->createIdentity($id);
        $session = $this->createSession();

        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository(), $this->createEventDispatcher()))
            ->withAuthTimeout(60)
            ->withSession($session)
        ;

        $AuthManager->login($identity);

        $this->assertTrue($session->has('__auth_expire'));
        $this->assertSame($identity, $AuthManager->getIdentity());

        // Second getting
        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withAuthTimeout(60)
            ->withSession($session)
        ;

        $this->assertSame($identity, $AuthManager->getIdentity());

        // Third getting
        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository($identity), $this->createEventDispatcher()))
            ->withAuthTimeout(60)
            ->withSession($session)
        ;

        $this->assertSame($identity, $AuthManager->getIdentity());
    }

    public function testSuccessfulLogout(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository(), $eventDispatcher))
            ->withSession($this->createSession())
        ;

        $AuthManager->login($this->createIdentity('test-id'));

        $this->assertTrue($AuthManager->logout());
        $this->assertTrue(!$AuthManager->isAuthenticated());

        $this->assertCount(4, $eventDispatcher->getEvents());
        $this->assertSame(
            [
                BeforeLogin::class,
                AfterLogin::class,
                BeforeLogout::class,
                AfterLogout::class,
            ],
            $eventDispatcher->getEventClasses(),
        );
    }

    public function testGuestLogout(): void
    {
        $eventDispatcher = $this->createEventDispatcher();

        $AuthManager = new AuthManager($this->createSession(), $this->createIdentityRepository(), $eventDispatcher);

        $this->assertFalse($AuthManager->logout());
        $this->assertEmpty($eventDispatcher->getEvents());
        $this->assertTrue(!$AuthManager->isAuthenticated());
    }

    public function testLogoutWithSetAuthExpire(): void
    {
        $session = $this->createSession();
        $sessionId = $session->getId();

        $AuthManager = (new AuthManager($this->createSession(), $this->createIdentityRepository(), $this->createEventDispatcher()))
            ->withAbsoluteAuthTimeout(3600)
            ->withAuthTimeout(60)
            ->withSession($session)
        ;

        $AuthManager->login($this->createIdentity('test-id'));
        $AuthManager->logout();

        $this->assertNotSame($sessionId, $session->getId());
        $this->assertFalse($session->has('__auth_id'));
        $this->assertFalse($session->has('__auth_expire'));
        $this->assertFalse($session->has('__auth_absolute_expire'));
        $this->assertInstanceOf(GuestIdentity::class, $AuthManager->getIdentity());
    }

    public static function dataCan(): iterable
    {
        $accessChecker = new AccessCheckerStub(['edit']);
        yield 'without access checker' => [false, 'edit', null];
        yield 'string false' => [false, 'delete', $accessChecker];
        yield 'string true' => [true, 'edit', $accessChecker];
        yield 'enum false' => [false, Permission::DELETE, $accessChecker];
        yield 'enum true' => [true, Permission::EDIT, $accessChecker];
    }

    #[DataProvider('dataCan')]
    public function testCan(
        bool $expected,
        string|BackedEnum $permissionName,
        ?AccessCheckerInterface $accessChecker,
    ): void {
        $AuthManager = new AuthManager($this->createSession(), $this->createIdentityRepository(), $this->createEventDispatcher());
        if ($accessChecker !== null) {
            $AuthManager = $AuthManager->withAccessChecker($accessChecker);
        }

        $this->assertSame($expected, $AuthManager->can($permissionName));
    }

    public function testImmutable(): void
    {
        $AuthManager = new AuthManager($this->createSession(), $this->createIdentityRepository(), $this->createEventDispatcher());

        $this->assertNotSame($AuthManager, $AuthManager->withSession($this->createSession()));
        $this->assertNotSame($AuthManager, $AuthManager->withAccessChecker(new AccessCheckerStub()));
        $this->assertNotSame($AuthManager, $AuthManager->withAbsoluteAuthTimeout(3600));
        $this->assertNotSame($AuthManager, $AuthManager->withAuthTimeout(60));
    }

    private function createIdentity(string $id): IdentityInterface
    {
        return new MockIdentity($id);
    }

    private function createSession(array $data = []): MockArraySessionStorage
    {
        return new MockArraySessionStorage($data);
    }

    private function createIdentityRepository(?IdentityInterface $identity = null): IdentityRepositoryInterface
    {
        return new MockIdentityRepository($identity);
    }

    private function createEventDispatcher(): SimpleEventDispatcher
    {
        return new SimpleEventDispatcher();
    }
}
