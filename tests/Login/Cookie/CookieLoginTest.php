<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Auth\Session\Tests\Login\Cookie;

use DateInterval;
use HttpSoft\Message\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yiisoft\Yii\Auth\Session\Login\Cookie\CookieLogin;
use Yiisoft\Yii\Auth\Session\Tests\Support\CookieLoginIdentity;
use Yiisoft\Cookies\Cookie;

final class CookieLoginTest extends TestCase
{
    public function testAddCookie(): void
    {
        $cookieLogin = new CookieLogin(new DateInterval('P1W'));

        $identity = new CookieLoginIdentity();

        $response = new Response();
        $response = $cookieLogin->addCookie($identity, $response);

        $this->assertMatchesRegularExpression(
            '#autoLogin=%5B%2242%22%2C%22auto-login-key-correct%22%2C[0-9]{10}%5D;'
            . ' Expires=.*?; Max-Age=604800; Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public function testAddSessionCookie(): void
    {
        $cookieLogin = new CookieLogin();

        $identity = new CookieLoginIdentity();

        $response = new Response();
        $response = $cookieLogin->addCookie($identity, $response);

        $this->assertMatchesRegularExpression(
            '#autoLogin=%5B%2242%22%2C%22auto-login-key-correct%22%2C0%5D;'
            . ' Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public function testAddCookieWithDisableCookieSecure(): void
    {
        $cookieLogin = new CookieLogin(cookieSecure: false);

        $identity = new CookieLoginIdentity();

        $response = new Response();
        $response = $cookieLogin->addCookie($identity, $response);

        $this->assertMatchesRegularExpression(
            '#autoLogin=%5B%2242%22%2C%22auto-login-key-correct%22%2C0%5D;'
            . ' Path=/; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public function testRemoveCookie(): void
    {
        $cookieLogin = new CookieLogin(new DateInterval('P1W'));

        $response = new Response();
        $response = $cookieLogin->expireCookie($response);

        $this->assertMatchesRegularExpression(
            '#autoLogin=; Expires=.*?; Max-Age=-\d++; Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public function testAddCookieWithCustomName(): void
    {
        $cookieName = 'testName';
        $cookieLogin = (new CookieLogin(new DateInterval('P1W')))->withCookieName($cookieName);

        $identity = new CookieLoginIdentity();

        $response = new Response();
        $response = $cookieLogin->addCookie($identity, $response);

        $this->assertMatchesRegularExpression(
            '#' . $cookieName . '=%5B%2242%22%2C%22auto-login-key-correct%22%2C[0-9]{10}%5D;'
            . ' Expires=.*?; Max-Age=604800; Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public function testRemoveCookieWithCustomName(): void
    {
        $cookieName = 'testName';
        $cookieLogin = (new CookieLogin(new DateInterval('P1W')))->withCookieName($cookieName);

        $response = new Response();
        $response = $cookieLogin->expireCookie($response);

        $this->assertMatchesRegularExpression(
            '#' . $cookieName . '=; Expires=.*?; Max-Age=-\d++; Path=/; Secure; HttpOnly; SameSite=Lax#',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public static function dataAddCookieWithCustomDuration(): array
    {
        return [
            'false' => [
                '#testName=%5B%2242%22%2C%22auto-login-key-correct%22%2C[0-9]{10}%5D; Expires=.*?; Max-Age=604800; Path=/; Secure; HttpOnly; SameSite=Lax#',
                false,
            ],
            'null' => [
                '#testName=%5B%2242%22%2C%22auto-login-key-correct%22%2C0%5D; Path=/; Secure; HttpOnly; SameSite=Lax#',
                null,
            ],
            'p1d' => [
                '#testName=%5B%2242%22%2C%22auto-login-key-correct%22%2C[0-9]{10}%5D; Expires=.*?; Max-Age=86400; Path=/; Secure; HttpOnly; SameSite=Lax#',
                new DateInterval('P1D'),
            ],
        ];
    }

    #[DataProvider('dataAddCookieWithCustomDuration')]
    public function testAddCookieWithCustomDuration(string $expectedRegExp, DateInterval|false|null $duration): void
    {
        $cookieLogin = (new CookieLogin(new DateInterval('P1W')))->withCookieName('testName');

        $identity = new CookieLoginIdentity();

        $response = new Response();
        $response = $cookieLogin->addCookie($identity, $response, $duration);

        $this->assertMatchesRegularExpression(
            $expectedRegExp,
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public function testAddSignedCookie(): void
    {
        $cookieLogin = new CookieLogin(signerKey: 'secret-key');

        $response = $cookieLogin->addCookie(new CookieLoginIdentity(), new Response());
        $setCookieHeaderValue = rawurldecode($this->extractCookieValue($response->getHeaderLine('Set-Cookie')));

        $cookieValue = json_encode(
            [CookieLoginIdentity::ID, CookieLoginIdentity::KEY_CORRECT, 0],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $cookie = new Cookie(name: $cookieLogin->getCookieName(), value: $cookieValue);
        $cookie = $cookieLogin->signer->sign($cookie);

        $this->assertSame($cookie->getValue(), $setCookieHeaderValue);
    }

    public function testAddEncryptedCookie(): void
    {
        $cookieLogin = new CookieLogin(encryptorKey: 'secret-key');

        $response = $cookieLogin->addCookie(new CookieLoginIdentity(), new Response());
        $setCookieHeaderValue = rawurldecode($this->extractCookieValue($response->getHeaderLine('Set-Cookie')));

        $cookieValue = json_encode(
            [CookieLoginIdentity::ID, CookieLoginIdentity::KEY_CORRECT, 0],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $this->assertSame($cookieValue, $cookieLogin->encryptor->decrypt(new Cookie(name: $cookieLogin->getCookieName(), value: $setCookieHeaderValue))->getValue());
    }

    public function testAddSignedAndEncryptedCookie(): void
    {
        $cookieLogin = new CookieLogin(encryptorKey: 'secret-key1', signerKey: 'secret-key2');

        $response = $cookieLogin->addCookie(new CookieLoginIdentity(), new Response());
        $setCookieHeaderValue = rawurldecode($this->extractCookieValue($response->getHeaderLine('Set-Cookie')));

        $cookieValue = json_encode(
            [CookieLoginIdentity::ID, CookieLoginIdentity::KEY_CORRECT, 0],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $cookie = new Cookie(name: $cookieLogin->getCookieName(), value: $cookieValue);
        $cookieSigned = $cookieLogin->signer->sign($cookie);

        $this->assertSame($cookieSigned->getValue(), $cookieLogin->encryptor->decrypt(new Cookie(name: $cookieLogin->getCookieName(), value: $setCookieHeaderValue))->getValue());
    }

    private function extractCookieValue(string $setCookieHeader): string
    {
        $pair = explode(';', $setCookieHeader, 2)[0];
        [, $value] = explode('=', $pair, 2);

        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }
}
