<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Attributes;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Dolphin\Attributes\RequiresPermission;
use StrictlyPHP\Tests\Dolphin\Fixtures\Authorization\TestPermission;
use StrictlyPHP\Tests\Dolphin\Fixtures\Authorization\UserKind;

class RequiresPermissionTest extends TestCase
{
    public function testRequiresPermissionAttribute(): void
    {
        $attribute = new RequiresPermission(
            UserKind::ADMIN,
            TestPermission::CREATE_USER
        );

        $this->assertSame(UserKind::ADMIN, $attribute->userKind);
        $this->assertSame(TestPermission::CREATE_USER, $attribute->permission);
    }
}
