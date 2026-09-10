<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Auth\Session\Login\Cookie;

use InvalidArgumentException;
use DateInterval;
use DateTimeImmutable;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Cookies\Cookie;
use Yiisoft\Cookies\CookieEncryptor;
use Yiisoft\Cookies\CookieSigner;

use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The service is used to send or remove auto-login cookie.
 *
 * @see CookieLoginIdentityInterface
 * @see CookieLoginMiddleware
 */
final class CookieLogin
{
    private string $cookieName = 'autoLogin';

    public readonly ?CookieEncryptor $encryptor;
    public readonly ?CookieSigner $signer;

    /**
     * @param DateInterval|null $duration Interval until the auto-login cookie expires. If it isn't set it means
     * the auto-login cookie is session cookie that expires when browser is closed.
     * @param bool $cookieSecure Whether the client should send back the cookie only over HTTPS connection.
     * @param string|null $encryptorKey Secret key to encrypt the auto-login cookie value, set it to `null` to store it unencrypted.
     * @param string|null $signerKey Secret key to sign the auto-login cookie value, set it to `null` to store it unsigned.
     */
    public function __construct(
        private ?DateInterval $duration = null,
        private readonly bool $cookieSecure = true,
        ?string               $encryptorKey = null,
        ?string               $signerKey = null,
    ) {
        if ($encryptorKey !== null) {
            if ($encryptorKey !== '') {
                $this->encryptor = new CookieEncryptor($encryptorKey);
            } else {
                throw new InvalidArgumentException("The 'encryptorKey' argument must be set through the 'yiisoft/yii-auth-session' => ['cookieLogin' => ['encryptorKey' => 'your secret key']] in config/web/params.php.");
            }
        } else {
            $this->encryptor = null;
        }
        if ($signerKey !== null) {
            if ($signerKey !== '') {
                $this->signer = new CookieSigner($signerKey);
            } else {
                throw new InvalidArgumentException("The 'signerKey' argument must be set through the 'yiisoft/yii-auth-session' => ['cookieLogin' => ['signerKey' => 'your secret key']] in config/web/params.php.");
            }
        } else {
            $this->signer = null;
        }
    }

    /**
     * Returns a new instance with the specified auto-login cookie name.
     *
     * @param string $name The auto-login cookie name.
     */
    public function withCookieName(string $name): self
    {
        $new = clone $this;
        $new->cookieName = $name;
        return $new;
    }

    /**
     * Adds auto-login cookie to response so the user is logged in automatically based on cookie even if session
     * is expired.
     *
     * @param CookieLoginIdentityInterface $identity The cookie login identity instance.
     * @param ResponseInterface $response Response for adding auto-login cookie.
     * @param DateInterval|false|null $duration Interval until the auto-login cookie expires. If it is null it means
     * the auto-login cookie is session cookie that expires when browser is closed. If it is false (by default) will be
     * used default value of duration.
     *
     * @return ResponseInterface Response with added auto-login cookie.
     * @throws JsonException If an error occurs during JSON encoding of the cookie value.
     *
     */
    public function addCookie(
        CookieLoginIdentityInterface $identity,
        ResponseInterface            $response,
        DateInterval|false|null      $duration = false,
    ): ResponseInterface {
        $duration = $duration === false ? $this->duration : $duration;

        $data = [$identity->getId(), $identity->getCookieLoginKey()];

        if ($duration !== null) {
            $expires = (new DateTimeImmutable())->add($duration);
            $data[] = $expires->getTimestamp();
        } else {
            $expires = null;
            $data[] = 0;
        }

        $cookieValue = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $cookie = new Cookie(name: $this->cookieName, value: $cookieValue, expires: $expires, secure: $this->cookieSecure);
        if ($this->signer !== null) {
            $cookie = $this->signer->sign($cookie);
        }
        if ($this->encryptor !== null) {
            $cookie = $this->encryptor->encrypt($cookie);
        }

        return $cookie->addToResponse($response);
    }

    /**
     * Expires auto-login cookie so user is not logged in automatically anymore.
     *
     * @param ResponseInterface $response Response for adding auto-login cookie.
     *
     * @return ResponseInterface Response with added auto-login cookie.
     */
    public function expireCookie(ResponseInterface $response): ResponseInterface
    {
        return (new Cookie(name: $this->cookieName, secure: $this->cookieSecure))
            ->expire()
            ->addToResponse($response);
    }

    /**
     * Returns the auto-login cookie name.
     *
     * @return string The auto-login cookie name.
     */
    public function getCookieName(): string
    {
        return $this->cookieName;
    }
}
