<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Attributes;

use Attribute;
use StrictlyPHP\Dolphin\Authorization\RoleInterface;

/**
 * Widens access for a role: a user holding one of the listed roles passes
 * regardless of the route's #[RequiresRole]/#[RequiresRoles] or
 * #[RequiresPermission] gates (the permission check — and its
 * AuthorizationServiceInterface — is skipped entirely for them).
 *
 * Repeatable, with ANY-of (logical OR) semantics. Purely additive: it grants,
 * it never restricts. A route carrying ONLY #[AllowsRole] declares no gate, so
 * it is effectively open — to *restrict* access to a role, use #[RequiresRole].
 *
 * Typical use is alongside #[RequiresPermission], e.g. restrict to a fine-grained
 * permission but let a back-office role straight through.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class AllowsRole
{
    public function __construct(
        public readonly RoleInterface $role
    ) {
    }
}
