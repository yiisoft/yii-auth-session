<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Auth\Session\Tests\Event;

use Yiisoft\Yii\Auth\Session\Event\AfterLogout;
use PHPUnit\Framework\TestCase;
use Yiisoft\Yii\Auth\Session\Tests\Support\MockIdentity;

final class AfterLogoutTest extends TestCase
{
    public function testGetIdentity(): void
    {
        $identity = new MockIdentity('test');

        $event = new AfterLogout($identity);

        $this->assertSame($identity, $event->getIdentity());
    }
}
