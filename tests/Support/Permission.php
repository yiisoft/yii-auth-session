<?php

declare(strict_types=1);

namespace Yiisoft\Yii\Auth\Session\Tests\Support;

enum Permission: string
{
    case EDIT = 'edit';
    case DELETE = 'delete';
}
