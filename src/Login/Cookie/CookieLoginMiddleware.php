<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Auth\Session\Login\Cookie;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Yiisoft\Auth\IdentityRepositoryInterface;
use Yiisoft\Yii\Auth\Session\AuthManager;
use Yiisoft\Cookies\Cookie;

use function array_key_exists;
use function count;
use function is_array;
use function json_decode;
use function sprintf;
use function time;

use const JSON_THROW_ON_ERROR;

/**
 * `CookieLoginMiddleware` automatically logs user in based on cookie.
 */
final class CookieLoginMiddleware implements MiddlewareInterface
{
    /**
     * @param AuthManager $authManager The AuthManager instance.
     * @param IdentityRepositoryInterface $identityRepository The identity repository instance.
     * @param LoggerInterface $logger The logger instance.
     * @param CookieLogin $cookieLogin The cookie login instance.
     * @param bool $forceAddCookie Whether to force add a cookie.
     */
    public function __construct(
        private AuthManager $authManager,
        private IdentityRepositoryInterface $identityRepository,
        private LoggerInterface $logger,
        private CookieLogin $cookieLogin,
        private bool $forceAddCookie = false,
    ) {}

    /**
     * {@inheritDoc}
     *
     * @throws JsonException If an error occurs when JSON encoding the cookie value while adding the cookie file.
     * @throws RuntimeException If during authentication, the identity repository {@see IdentityRepositoryInterface}
     * does not return an instance of {@see CookieLoginIdentityInterface}.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->authManager->isAuthenticated()) {
            $this->authenticateUserByCookieFromRequest($request);
        }

        $guestBeforeHandle = !$this->authManager->isAuthenticated();
        $response = $handler->handle($request);
        $guestAfterHandle = !$this->authManager->isAuthenticated();

        if ($this->forceAddCookie && $guestBeforeHandle && !$guestAfterHandle) {
            $identity = $this->authManager->getIdentity();

            if ($identity instanceof CookieLoginIdentityInterface) {
                $response = $this->cookieLogin->addCookie($identity, $response);
            }
        }

        if (!$guestBeforeHandle && $guestAfterHandle) {
            $response = $this->cookieLogin->expireCookie($response);
        }

        return $response;
    }

    /**
     * Authenticate user by auto-login cookie from request.
     *
     * @param ServerRequestInterface $request Request instance containing auto-login cookie.
     *
     * @throws RuntimeException If the identity repository {@see IdentityRepositoryInterface}
     * does not return an instance of {@see CookieLoginIdentityInterface}.
     */
    private function authenticateUserByCookieFromRequest(ServerRequestInterface $request): void
    {
        $cookieName = $this->cookieLogin->getCookieName();
        $cookies = $request->getCookieParams();

        if (!array_key_exists($cookieName, $cookies)) {
            return;
        }

        try {
            if ($this->cookieLogin->encryptor !== null) {
                $cookie = $this->cookieLogin->encryptor->decrypt(new Cookie($cookieName, $cookies[$cookieName]));
                $cookies[$cookieName] = $cookie->getValue();
            }
            if ($this->cookieLogin->signer !== null) {
                $cookie = $this->cookieLogin->signer->validate(new Cookie($cookieName, $cookies[$cookieName]));
                $cookies[$cookieName] = $cookie->getValue();
            }
        } catch (RuntimeException $exception) {
            $this->logger->warning($exception->getMessage());
            return;
        }

        try {
            $data = json_decode((string) $cookies[$cookieName], true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $this->logger->warning('Unable to authenticate user by cookie. Invalid cookie.');
            return;
        }

        if (!is_array($data) || count($data) !== 3) {
            $this->logger->warning('Unable to authenticate user by cookie. Invalid cookie.');
            return;
        }

        [$id, $key, $expires] = $data;

        $id = (string) $id;
        $key = (string) $key;
        $expires = (int) $expires;

        $identity = $this->identityRepository->findIdentity($id);

        if ($identity === null) {
            $this->logger->warning("Unable to authenticate user by cookie. Identity \"$id\" not found.");
            return;
        }

        if (!$identity instanceof CookieLoginIdentityInterface) {
            throw new RuntimeException(
                sprintf(
                    'Identity repository must return an instance of %s in order for auto-login to function.',
                    CookieLoginIdentityInterface::class,
                ),
            );
        }

        if (!$identity->validateCookieLoginKey($key)) {
            $this->logger->warning('Unable to authenticate user by cookie. Invalid key.');
            return;
        }

        if ($expires !== 0 && $expires < time()) {
            $this->logger->warning('Unable to authenticate user by cookie. Lifetime has expired.');
            return;
        }

        $this->authManager->login($identity);
    }
}
