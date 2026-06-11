<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Strategy;

use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunction;
use ReflectionMethod;

/**
 * App-provided route enforcement that runs after Dolphin's built-in
 * AccessControlEnforcer (which handles #[RequiresRoles], #[RequiresPermission],
 * etc.) but before the controller body executes.
 *
 * Implementations reflect on the route handler for whatever class-level
 * attributes they own, read state from the request and path vars, and throw
 * `League\Route\Http\Exception\ForbiddenException` (or `UnauthorizedException`)
 * on failure. Return the request — optionally with new attributes set — to
 * pass control onward.
 *
 * Dolphin invokes each registered enforcer in registration order on every
 * matched route. An enforcer with no work to do for the current route
 * should return $request unchanged.
 */
interface RouteEnforcerInterface
{
    /**
     * @param array<string, string> $vars Path variables extracted by League Route
     *                                    (the same array passed to attribute-typed
     *                                    `array $vars` controller params).
     */
    public function enforce(
        ReflectionMethod|ReflectionFunction $ref,
        ServerRequestInterface $request,
        array $vars,
    ): ServerRequestInterface;
}
