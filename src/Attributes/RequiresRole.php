<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Attributes;

use Attribute;
use StrictlyPHP\Dolphin\Authorization\RoleInterface;

/**
 * Declares a role required to access a route handler. The enum-only,
 * repeatable counterpart to #[RequiresRoles]: each instance contributes one
 * role, and multiple instances combine with ANY-of (logical OR) semantics —
 * the user needs at least one of the listed roles.
 *
 * Gates access (produces a 403 when unmet). Use #[AllowsRole] to widen access
 * for additional roles without restricting.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class RequiresRole
{
    public function __construct(
        public readonly RoleInterface $role
    ) {
    }
}
