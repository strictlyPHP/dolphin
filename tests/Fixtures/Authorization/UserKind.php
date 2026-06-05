<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures\Authorization;

use StrictlyPHP\Dolphin\Authorization\RoleInterface;

enum UserKind: string implements RoleInterface
{
    case ADMIN = 'ADMIN';
    case USER = 'USER';
}
