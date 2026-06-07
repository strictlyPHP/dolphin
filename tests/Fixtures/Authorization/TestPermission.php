<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures\Authorization;

use StrictlyPHP\Dolphin\Authorization\PermissionInterface;

enum TestPermission: string implements PermissionInterface
{
    case CREATE_USER = 'CREATE_USER';
    case DELETE_USER = 'DELETE_USER';
}
