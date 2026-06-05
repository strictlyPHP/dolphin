<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Strategy;

use League\Route\Http\Exception\ForbiddenException;
use League\Route\Http\Exception\UnauthorizedException;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunction;
use ReflectionMethod;
use StrictlyPHP\Dolphin\Attributes\RequiresPermission;
use StrictlyPHP\Dolphin\Attributes\RequiresRoles;
use StrictlyPHP\Dolphin\Authentication\AuthenticatedUserInterface;
use StrictlyPHP\Dolphin\Authorization\AuthorizationServiceInterface;

/**
 * Enforces #[RequiresRoles] and #[RequiresPermission] attributes declared
 * on a matched route handler.
 */
class AccessControlEnforcer
{
    public function __construct(
        private ?AuthorizationServiceInterface $authorizationService = null
    ) {
    }

    /**
     * Runs role enforcement first, then permission enforcement.
     * Returns the request with the 'required_roles' attribute set.
     */
    public function enforce(
        ReflectionMethod|ReflectionFunction $ref,
        ServerRequestInterface $request
    ): ServerRequestInterface {
        $request = $this->enforceRoles($ref, $request);
        $this->enforcePermissions($ref, $request);

        return $request;
    }

    private function enforceRoles(
        ReflectionMethod|ReflectionFunction $ref,
        ServerRequestInterface $request
    ): ServerRequestInterface {
        $requiredRoles = [];

        // Class-level attributes
        $classAttrs = $ref->getDeclaringClass()->getAttributes(RequiresRoles::class);
        if (! empty($classAttrs)) {
            $instance = $classAttrs[0]->newInstance();
            $requiredRoles = array_merge($requiredRoles, $instance->roles);
        }
        $request = $request->withAttribute('required_roles', $requiredRoles);

        if (! empty($requiredRoles)) {
            $user = $this->getAuthenticatedUser($request);

            // Intersect the required roles with the user roles
            if (empty(array_intersect($user->getRoles(), $requiredRoles))) {
                throw new ForbiddenException(
                    'User does not have permission to access this resource'
                );
            }
        }

        return $request;
    }

    private function enforcePermissions(
        ReflectionMethod|ReflectionFunction $ref,
        ServerRequestInterface $request
    ): void {
        $permissionAttrs = $ref->getDeclaringClass()->getAttributes(RequiresPermission::class);
        if (empty($permissionAttrs)) {
            return;
        }

        if ($this->authorizationService === null) {
            throw new \RuntimeException(
                'Route handler declares #[RequiresPermission] but no AuthorizationServiceInterface is bound. ' .
                'Inject one into DolphinAppStrategy.'
            );
        }

        $user = $this->getAuthenticatedUser($request);

        // ANY-of semantics: the user needs at least one of the listed permissions
        $allowed = false;
        foreach ($permissionAttrs as $permissionAttr) {
            $requiresPermission = $permissionAttr->newInstance();
            if ($this->authorizationService->isAllowed(
                $user,
                $requiresPermission->userKind,
                $requiresPermission->permission
            )) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            throw new ForbiddenException(
                'User does not have permission to access this resource'
            );
        }
    }

    private function getAuthenticatedUser(ServerRequestInterface $request): AuthenticatedUserInterface
    {
        // Your auth system sets this earlier in global middleware
        $user = $request->getAttribute('user');

        if (! $user) {
            throw new UnauthorizedException(
                'User is not authenticated'
            );
        }

        if (! $user instanceof AuthenticatedUserInterface) {
            throw new \RuntimeException(
                sprintf('Authenticated user must implement %s', AuthenticatedUserInterface::class)
            );
        }

        return $user;
    }
}
