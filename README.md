<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="100px" alt="Yii">
    </a>
    <h1 align="center">Yii Auth Session</h1>
    <br>
</p>

[![Latest Stable Version](https://poser.pugx.org/yiisoft/yii-auth-session/v)](https://packagist.org/packages/yiisoft/yii-auth-session)
[![Total Downloads](https://poser.pugx.org/yiisoft/yii-auth-session/downloads)](https://packagist.org/packages/yiisoft/yii-auth-session)
[![Build status](https://github.com/yiisoft/yii-auth-session/actions/workflows/build.yml/badge.svg)](https://github.com/yiisoft/yii-auth-session/actions/workflows/build.yml)
[![Code Coverage](https://codecov.io/gh/yiisoft/yii-auth-session/graph/badge.svg?token=ILNFVY8C26)](https://codecov.io/gh/yiisoft/yii-auth-session)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fuser%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/yii-auth-session/master)
[![static analysis](https://github.com/yiisoft/yii-auth-session/workflows/static%20analysis/badge.svg)](https://github.com/yiisoft/yii-auth-session/actions?query=workflow%3A%22static+analysis%22)
[![type-coverage](https://shepherd.dev/github/yiisoft/yii-auth-session/coverage.svg)](https://shepherd.dev/github/yiisoft/yii-auth-session)

The package manages user-related functionality via session-based authentication:

- Login and logout.
- Getting currently logged in identity.
- Changing current identity.
- Access checking for current user.
- Auto login based on identity from request attribute.
- Auto login or "remember me" based on request cookie.

## Requirements

- PHP 8.1 - 8.5.

## Installation

The package could be installed with [Composer](https://getcomposer.org):

```shell
composer require yiisoft/yii-auth-session
```

## General usage

This package is an addition to [yiisoft/auth](https://github.com/yiisoft/auth) and provides additional functionality for interacting with user identity.

### Working with identity

The `AuthManager` lass is responsible for handling logins and logouts, as well as providing information about the current user identity.

```php
/** 
 * @var \Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher
 * @var \Yiisoft\Auth\IdentityRepositoryInterface $identityRepository
 */

$authManager = new \Yiisoft\Yii\Auth\Session\AuthManager($identityRepository, $eventDispatcher);
```

If the user has not been logged in, then the current user is a guest:

```php
$authManager->getIdentity(); // \Yiisoft\Yii\Auth\Session\Guest\GuestIdentity instance
$authManager->getId(); // null
!$authManager->isAuthenticated(); // bool
```

If you need to use a custom identity class to represent guest user, you should pass an instance
of `GuestIdentityFactoryInterface` as a third optional parameter when creating `AuthManager`:

```php
/** 
 * @var \Psr\EventDispatcher\EventDispatcherInterface $eventDispatcher
 * @var \Yiisoft\Auth\IdentityRepositoryInterface $identityRepository
 * @var \Yiisoft\Yii\Auth\Session\Guest\GuestIdentityFactoryInterface $guestIdentityFactory
 */

$authManager = new \Yiisoft\Yii\Auth\Session\AuthManager($identityRepository, $eventDispatcher, $guestIdentityFactory);
```

Also, you can override an identity instance in runtime:

```php
/** 
 * @var \Yiisoft\Auth\IdentityInterface $identity
 */

$authManager->getIdentity(); // Original identity instance
$authManager->overrideIdentity($identity);
$authManager->getIdentity(); // Override identity instance
$authManager->clearIdentityOverride();
$authManager->getIdentity(); // Original identity instance
```

It can be useful to allow admin or developer to validate another user's problems.

#### Login and logout

There are two methods for login and logout:

```php
/**
 * @var \Yiisoft\Auth\IdentityInterface $identity
 */

$authManager->getIdentity(); // GuestIdentityInterface instance

if ($authManager->login($identity)) {
    $authManager->getIdentity(); // $identity
    // Some actions
}

if ($authManager->logout()) {
    $authManager->getIdentity(); // GuestIdentityInterface instance
    // Some actions
}
```

Both methods trigger events. Events are of the following classes:

- `Yiisoft\Yii\Auth\Session\Event\BeforeLogin` - triggered at the beginning of login process.
  Listeners of this event may call `$event->invalidate()` to cancel the login process.
- `Yiisoft\Yii\Auth\Session\Event\AfterLogin` - triggered at the ending of login process.
- `Yiisoft\Yii\Auth\Session\Event\BeforeLogout` - triggered at the beginning of logout process.
  Listeners of this event may call `$event->invalidate()` to cancel the logout process.
- `Yiisoft\Yii\Auth\Session\Event\AfterLogout` - triggered at the ending of logout process.

Listeners of these events can get an identity instance participating in the process using `$event->getIdentity()`.
Events are dispatched by `Psr\EventDispatcher\EventDispatcherInterface` instance, which is specified in the
constructor when the `Yiisoft\Yii\Auth\Session\AuthManager` instance is initialized.

#### Checking user access

To be able to check whether the current user can perform an operation corresponding to a given permission,
you need to set an access checker (see [yiisoft/access](https://github.com/yiisoft/access)) instance:

```php
/** 
 * @var \Yiisoft\Access\AccessCheckerInterface $accessChecker
 */
 
$authManager = $authManager->withAccessChecker($accessChecker);
```

To perform the check, use `can()` method:

```php
// The name of the permission (e.g. "edit post") that needs access check.
$permissionName = 'edit-post'; // Required.

// Name-value pairs that would be passed to the rules associated with the roles and permissions assigned to the user.
$params = ['postId' => 42]; // Optional. Default is empty array.

if ($authManager->can($permissionName, $params)) {
    // Some actions
}
```

Note that in case access checker is not provided via `withAccessChecker()` method, `can()` will always return `false`.

#### Session usage

The `AuthManager` can store user ID and authentication timeouts for auto-login in the session.

You can set timeout (number of seconds), during which the user will be logged out automatically in case of remaining inactive:

```php
$authManager = $authManager->withAuthTimeout(3600);
```

Also, an absolute timeout (number of seconds) could be used. The user will be logged
out automatically regardless of activity:

```php
$authManager = $authManager->withAbsoluteAuthTimeout(3600);
```

By default, timeouts are not used, so the user will be logged out after the current session expires.

#### Using with event loop

The `Yiisoft\Yii\Auth\Session\AuthManager` instance is stateful, so when you build long-running applications
with tools like [Swoole](https://www.swoole.co.uk/) or [RoadRunner](https://roadrunner.dev/) you should reset
the state at every request. For this purpose, you can use the `clear()` method.

### Authenticators

This package provides two authenticators, `AuthenticatorWithRedirectToAuthUrl` and `Authenticator`, which implement the `Yiisoft\Auth\AuthenticatorWithChallengeInterface` and the `Yiisoft\Auth\AuthenticatorInterface` interfaces accordingly. Both can be provided to the `Yiisoft\Auth\Authentication` middleware as authentication method.

#### AuthenticatorWithRedirectToAuthUrl

The `AuthenticatorWithRedirectToAuthUrl` is used to authenticate users of classic web applications.
If authentication is failed, it creates a new instance of the response and adds a `Location` header with a temporary redirect to the authentication URL, by default `/login`.

You can change authentication URL by calling `AuthenticatorWithRedirectToAuthUrl::withAuthUrl()` method:

```php
use Yiisoft\Yii\Auth\Session\AuthenticatorWithRedirectToAuthUrl;

$authenticationMethod = new AuthenticatorWithRedirectToAuthUrl();

// Returns a new instance with the specified authentication URL.
$authenticationMethod = $authenticationMethod->withAuthUrl('/auth');
```

or in the [DI container](https://github.com/yiisoft/di):

```php
// config/web/di/auth.php
return [
    AuthenticatorWithRedirectToAuthUrl::class => [
        'withAuthUrl()' => ['/auth'],
    ],
];
```

or through the parameter `authUrl` of the `yiisoft/yii-auth-session` config group, [yiisoft/config](https://github.com/yiisoft/config) package must be installed:

```php
// config/web/params.php
return [
    'yiisoft/yii-auth-session' => [
        'authUrl' => '/auth',
    ],
];
```

If the application is used along with the [yiisoft/config](https://github.com/yiisoft/config), the package is [configured](./config/di-web.php) automatically to use `AuthenticatorWithRedirectToAuthUrl` as default implementation of `Yiisoft\Auth\AuthenticatorInterface`.

#### Authenticator

The `Authenticator` is used for authenticating web applications that do not require a temporary redirect to an authentication URL.
If authentication is failed, it returns the response from the `Yiisoft\Auth\Middleware\Authentication::authenticationFailureHandler` handler.

To use `Authenticator` as an authentication method, you need or provide the `Authenticator` instance to the `Yiisoft\Auth\Middleware\Authentication` middleware:

```php
use Yiisoft\Auth\Middleware\Authentication;
use Yiisoft\Yii\Auth\Session\Authenticator;

$authenticationMethod = new Authenticator();

$middleware = new Authentication(
    $authenticationMethod,
    $responseFactory // PSR-17 ResponseFactoryInterface
);
```

of to define it as an implementation of `Yiisoft\Auth\AuthenticatorInterface` in the [DI container](https://github.com/yiisoft/di) configuration:

```php
// config/web/di/auth.php
use Yiisoft\Auth\AuthenticatorInterface;
use Yiisoft\Yii\Auth\Session\Authenticator;

return [
    AuthenticatorInterface::class => Authenticator::class,
];
```

For more information about the authentication middleware and authentication methods, see the [yiisoft/auth](https://github.com/yiisoft/auth).

### Auto login through identity from request attribute

For auto login, you can use the `Yiisoft\Yii\Auth\Session\Login\LoginMiddleware`. This middleware automatically logs user
in if `Yiisoft\Auth\IdentityInterface` instance presents in a request attribute. It is usually put there by
`Yiisoft\Auth\Middleware\Authentication`.

> Please note that `Yiisoft\Auth\Middleware\Authentication` should be located before
> `Yiisoft\Yii\Auth\Session\Login\LoginMiddleware` in the middleware stack.

### Auto login through cookie

In order to log user in automatically based on request cookie presence,
use `Yiisoft\Yii\Auth\Session\Login\Cookie\CookieLoginMiddleware`.

To use a middleware, you need to implement `Yiisoft\Yii\Auth\Session\Login\Cookie\CookieLoginIdentityInterface`
and also implement and use an instance of `IdentityRepositoryInterface` in the `AuthManager`,
which will return `CookieLoginIdentityInterface`:

```php
use App\CookieLoginIdentity;
use Yiisoft\Auth\IdentityRepositoryInterface;

final class CookieLoginIdentityRepository implements IdentityRepositoryInterface
{
    private Storage $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    public function findIdentity(string $id): ?Identity
    {   
        return new CookieLoginIdentity($this->storage->findOne($id));
    }
}
```

The `CookieLoginMiddleware` will check for the existence of a cookie in the request,
validate it and login the user automatically.

> [!warning]
> The auto-login cookie value needs to be configured. See
> [Protecting the cookie value](#protecting-the-cookie-value) below.

#### Creating a cookie

By default, you should set cookie for auto login manually in your application after logging user in:

```php
public function login(
    \Psr\Http\Message\ServerRequestInterface $request,
    \Psr\Http\Message\ResponseFactoryInterface $responseFactory,
    \Yiisoft\Yii\Auth\Session\Login\Cookie\CookieLogin $cookieLogin,
    \Yiisoft\Yii\Auth\Session\AuthManager $authManager
): \Psr\Http\Message\ResponseInterface {
    $response = $responseFactory->createResponse();
    $body = $request->getParsedBody();
    
    // Get user identity based on body content.
    
    /** @var \Yiisoft\Yii\Auth\Session\Login\Cookie\CookieLoginIdentityInterface $identity */
    
    if ($authManager->login($identity) && ($body['rememberMe'] ?? false)) {
        $response = $cookieLogin->addCookie($identity, $response);
    }
    
    return $response;
}
```

In the above `rememberMe` in the request body may come from a "remember me" checkbox in the form. End user decides
if he wants to be logged in automatically. If you do not need the user to be able to choose and want to always use
"remember me", you can enable it via the `forceAddCookiePolicy` in `params.php`:

```php
return [
    'yiisoft/yii-auth-session' => [
        'authUrl' => '/login',
        'cookieLogin' => [
            'forceAddCookiePolicy' => ForceAddCookiePolicy::Never, // AfterLoginForce adds the auto-login cookie only after login, while RenewDuringRequest adds/renews the auto-login cookie during each authenticated request
            'duration' => 'P5D', // 5 days
            'cookieSecure' => false, // whether the client should send back the cookie only over HTTPS connection
            'encryptorKey' => 'your-secret-random-string', // secret key to encrypt the auto-login cookie value, set it to `null` to store it unencrypted
            'signerKey' => 'your-secret-random-string'  // secret key to sign the auto-login cookie value, set it to `null` to store it unsigned
        ],
    ],
];
```

> If you want the cookie to be a session cookie, change the duration to `null`.

#### Removing a cookie

The `Yiisoft\Yii\Auth\Session\Login\Cookie\CookieLoginMiddleware` automatically removes the cookie after the logout.
But you can also remove the cookie manually:

```php
public function logout(
    \Psr\Http\Message\ResponseFactoryInterface $responseFactory,
    \Yiisoft\Yii\Auth\Session\Login\Cookie\CookieLogin $cookieLogin,
    \Yiisoft\Yii\Auth\Session\AuthManager $authManager
): \Psr\Http\Message\ResponseInterface {
    $response = $responseFactory
        ->createResponse(302)
        ->withHeader('Location', '/');
    
    // Regenerate cookie login key to `Yiisoft\Yii\Auth\Session\Login\Cookie\CookieLoginIdentityInterface` instance.
    
    if ($authManager->logout()) {
        $response = $cookieLogin->expireCookie($response);
    }
    
    return $response;
}
```

#### Protecting the cookie value

Without protection, the auto-login cookie value is stored as raw JSON: `[id, key, expires]`.
Anyone able to edit the cookie value — the end user, or an attacker who obtained the cookie — can change the
identity or the expiration timestamp.

To protect the cookie value from being read or tampered with, set the`encryptorKey` and/or `signerKey` parameters in the `config/web/params.php`:

```php
return [
    'yiisoft/yii-auth-session' => [
        'cookieLogin' => [
            'encryptorKey' => 'your-secret-random-string', // secret key to encrypt the auto-login cookie value, set it to `null` to store it unencrypted
            'signerKey' => 'your-secret-random-string'  // secret key to sign the auto-login cookie value, set it to `null` to store it unsigned
        ],
    ],
];
```

When the `encryptorKey` is set, `CookieLogin` encrypts the cookie to make it unreadable.

When the `signerKey` is set, `CookieLogin` signs the cookie value with HMAC-SHA256. `CookieLoginMiddleware` then rejects any auto-login cookie whose signature is missing or invalid.

Changing the keys invalidates all existing auto-login cookies. Use a long random string, keep it secret and do not reuse it for other purposes.


## Documentation

- [Internals](docs/internals.md)

If you need help or have a question, the [Yii Forum](https://forum.yiiframework.com/c/yii-3-0/63) is a good place for
that. You may also check out other [Yii Community Resources](https://www.yiiframework.com/community).

## License

The Yii Auth Session is free software. It is released under the terms of the BSD License.
Please see [`LICENSE`](./LICENSE.md) for more information.

Maintained by [Yii Software](https://www.yiiframework.com/).

## Support the project

[![Open Collective](https://img.shields.io/badge/Open%20Collective-sponsor-7eadf1?logo=open%20collective&logoColor=7eadf1&labelColor=555555)](https://opencollective.com/yiisoft)

## Follow updates

[![Official website](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat)](https://www.yiiframework.com/)
[![Twitter](https://img.shields.io/badge/twitter-follow-1DA1F2?logo=twitter&logoColor=1DA1F2&labelColor=555555?style=flat)](https://twitter.com/yiiframework)
[![Telegram](https://img.shields.io/badge/telegram-join-1DA1F2?style=flat&logo=telegram)](https://t.me/yii3en)
[![Facebook](https://img.shields.io/badge/facebook-join-1DA1F2?style=flat&logo=facebook&logoColor=ffffff)](https://www.facebook.com/groups/yiitalk)
[![Slack](https://img.shields.io/badge/slack-join-1DA1F2?style=flat&logo=slack)](https://yiiframework.com/go/slack)
