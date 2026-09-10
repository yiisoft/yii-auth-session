<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Auth\Session\Tests\Event;

use Yiisoft\Yii\Auth\Session\Event\BeforeLogout;
use PHPUnit\Framework\TestCase;
use Yiisoft\Yii\Auth\Session\Tests\Support\MockIdentity;

final class BeforeLogoutTest extends TestCase
{
    public function testGetIdentity(): void
    {
        $identity = new MockIdentity('test');

        $event = new BeforeLogout($identity);

        $this->assertSame($identity, $event->getIdentity());
    }

    public function testInvalidate(): void
    {
        $event = new BeforeLogout(new MockIdentity('test'));
        $event->invalidate();

        $this->assertFalse($event->isValid());
    }
}
