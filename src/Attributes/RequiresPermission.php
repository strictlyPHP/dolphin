<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Attributes;

use Attribute;
use StrictlyPHP\Dolphin\Authorization\PermissionInterface;
use StrictlyPHP\Dolphin\Authorization\RoleInterface;

/**
 * Declares that a route handler requires a permission, resolved through the
 * app-bound AuthorizationServiceInterface.
 *
 * Repeatable: multiple instances on one class mean ANY-of (logical OR) —
 * the user needs at least one of the listed permissions to be allowed.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class RequiresPermission
{
    public function __construct(
        public readonly RoleInterface $userKind,
        public readonly PermissionInterface $permission,
    ) {
    }
}
