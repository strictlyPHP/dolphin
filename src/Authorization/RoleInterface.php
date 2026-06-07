<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Authorization;

/**
 * Marker interface for enums whose cases identify a family of users
 * (users, admins). Consumers implement this on their own
 * backed enum; the app's AuthorizationServiceInterface knows how to
 * resolve the caller's concrete role within that family.
 */
interface RoleInterface extends \BackedEnum
{
}
