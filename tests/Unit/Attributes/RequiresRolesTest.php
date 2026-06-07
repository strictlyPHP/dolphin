<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Attributes;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Dolphin\Attributes\RequiresRoles;
use StrictlyPHP\Tests\Dolphin\Fixtures\Authorization\UserType;

class RequiresRolesTest extends TestCase
{
    public function testItAcceptsStrings(): void
    {
        $attribute = new RequiresRoles(['ADMIN', 'USER']);

        $this->assertSame(['ADMIN', 'USER'], $attribute->roles);
    }

    public function testItNormalisesRoleInterfaceEnumCasesToStrings(): void
    {
        $attribute = new RequiresRoles([UserType::ADMIN, UserType::USER]);

        $this->assertSame(['ADMIN', 'USER'], $attribute->roles);
    }

    public function testItAcceptsMixedStringsAndEnumCases(): void
    {
        $attribute = new RequiresRoles(['SUPPORT', UserType::ADMIN]);

        $this->assertSame(['SUPPORT', 'ADMIN'], $attribute->roles);
    }
}
