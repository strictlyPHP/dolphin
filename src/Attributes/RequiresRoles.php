<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Attributes;

use Attribute;
use StrictlyPHP\Dolphin\Authorization\RoleInterface;

#[Attribute(Attribute::TARGET_CLASS)]
class RequiresRoles
{
    /**
     * @var string[]
     */
    public readonly array $roles;

    /**
     * @param array<int, string|RoleInterface> $roles
     */
    public function __construct(array $roles)
    {
        $this->roles = array_map(
            static fn (string|RoleInterface $role): string => $role instanceof RoleInterface ? (string) $role->value : $role,
            $roles,
        );
    }
}
