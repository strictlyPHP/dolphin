<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Authorization;

use StrictlyPHP\Dolphin\Authentication\AuthenticatedUserInterface;

/**
 * App-provided authorisation policy. Returns true if $user (with whatever
 * runtime role/state they have) should be allowed to perform $permission on
 * a route declared for $userKind. Implementations encapsulate per-app rules:
 * matrix lookups, bypass policies for back-office roles, etc.
 */
interface AuthorizationServiceInterface
{
    public function isAllowed(
        AuthenticatedUserInterface $user,
        RoleInterface $userKind,
        PermissionInterface $permission,
    ): bool;
}
