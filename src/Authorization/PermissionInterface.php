<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Authorization;

/**
 * Marker interface for enums whose cases represent individual permissions.
 * Each user family typically has its own permission enum.
 */
interface PermissionInterface extends \BackedEnum
{
}
