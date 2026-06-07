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
     * Returns the request with the 'required_roles' and 'required_permissions'
     * attributes set.
     */
    public function enforce(
        ReflectionMethod|ReflectionFunction $ref,
        ServerRequestInterface $request
    ): ServerRequestInterface {
        $request = $this->enforceRoles($ref, $request);

        return $this->enforcePermissions($ref, $request);
    }

    private function enforceRoles(
        ReflectionMethod|ReflectionFunction $ref,
        ServerRequestInterface $request
    ): ServerRequestInterface {
        $requiredRoles = [];

        // Class-level attributes (function handlers have no declaring class)
        if ($ref instanceof ReflectionMethod) {
            $classAttrs = $ref->getDeclaringClass()->getAttributes(RequiresRoles::class);
            if (! empty($classAttrs)) {
                $instance = $classAttrs[0]->newInstance();
                $requiredRoles = array_merge($requiredRoles, $instance->roles);
            }
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
    ): ServerRequestInterface {
        $requiredPermissions = [];

        // Class-level attributes (function handlers have no declaring class)
        if ($ref instanceof ReflectionMethod) {
            $permissionAttrs = $ref->getDeclaringClass()->getAttributes(RequiresPermission::class);
            foreach ($permissionAttrs as $permissionAttr) {
                $requiredPermissions[] = $permissionAttr->newInstance();
            }
        }
        $request = $request->withAttribute('required_permissions', $requiredPermissions);

        if (empty($requiredPermissions)) {
            return $request;
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
        foreach ($requiredPermissions as $requiresPermission) {
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

        return $request;
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
