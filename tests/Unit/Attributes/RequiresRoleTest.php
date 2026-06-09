<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Attributes;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Dolphin\Attributes\RequiresRole;
use StrictlyPHP\Tests\Dolphin\Fixtures\Authorization\UserType;

class RequiresRoleTest extends TestCase
{
    public function testRequiresRoleAttribute(): void
    {
        $attribute = new RequiresRole(UserType::ADMIN);

        $this->assertSame(UserType::ADMIN, $attribute->role);
    }
}
