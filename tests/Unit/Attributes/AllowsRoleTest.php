<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Attributes;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Dolphin\Attributes\AllowsRole;
use StrictlyPHP\Tests\Dolphin\Fixtures\Authorization\UserType;

class AllowsRoleTest extends TestCase
{
    public function testAllowsRoleAttribute(): void
    {
        $attribute = new AllowsRole(UserType::ADMIN);

        $this->assertSame(UserType::ADMIN, $attribute->role);
    }
}
