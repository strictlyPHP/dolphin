<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Strategy;

use League\Route\Http\Exception\ForbiddenException;
use League\Route\Http\Exception\UnauthorizedException;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunction;
use ReflectionMethod;
use StrictlyPHP\Dolphin\Attributes\AllowsRole;
use StrictlyPHP\Dolphin\Attributes\RequiresPermission;
use StrictlyPHP\Dolphin\Attributes\RequiresRole;
use StrictlyPHP\Dolphin\Attributes\RequiresRoles;
use StrictlyPHP\Dolphin\Authentication\AuthenticatedUserInterface;
use StrictlyPHP\Dolphin\Authorization\AuthorizationServiceInterface;

/**
 * Enforces #[RequiresRoles], #[RequiresRole], #[RequiresPermission] and
 * #[AllowsRole] attributes declared on a matched route handler.
 */
class AccessControlEnforcer implements RouteEnforcerInterface
{
    public function __construct(
        private ?AuthorizationServiceInterface $authorizationService = null
    ) {
    }

    /**
     * Runs role enforcement first, then permission enforcement. A user holding
     * an #[AllowsRole] role bypasses both gates. Returns the request with the
     * 'required_roles' and 'required_permissions' attributes set.
     *
     * @param array<string, string> $vars Unused by the built-in enforcer;
     *                                    present to satisfy RouteEnforcerInterface.
     */
    public function enforce(
        ReflectionMethod|ReflectionFunction $ref,
        ServerRequestInterface $request,
        array $vars = []
    ): ServerRequestInterface {
        // #[AllowsRole] is purely additive: a user holding one of the allowed
        // roles passes regardless of the role/permission gates below.
        $bypass = $this->hasAllowingRole($ref, $request);

        $request = $this->enforceRoles($ref, $request, $bypass);

        return $this->enforcePermissions($ref, $request, $bypass);
    }

    private function enforceRoles(
        ReflectionMethod|ReflectionFunction $ref,
        ServerRequestInterface $request,
        bool $bypass
    ): ServerRequestInterface {
        $requiredRoles = [];

        // Class-level attributes (function handlers have no declaring class)
        if ($ref instanceof ReflectionMethod) {
            $class = $ref->getDeclaringClass();

            foreach ($class->getAttributes(RequiresRoles::class) as $rolesAttr) {
                $requiredRoles = array_merge($requiredRoles, $rolesAttr->newInstance()->roles);
            }

            foreach ($class->getAttributes(RequiresRole::class) as $roleAttr) {
                $requiredRoles[] = (string) $roleAttr->newInstance()->role->value;
            }
        }
        $request = $request->withAttribute('required_roles', $requiredRoles);

        if (! empty($requiredRoles) && ! $bypass) {
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
        ServerRequestInterface $request,
        bool $bypass
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

        if (empty($requiredPermissions) || $bypass) {
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

    /**
     * True when the request carries an authenticated user holding one of the
     * route's #[AllowsRole] roles. Non-throwing: an absent or wrong-typed user
     * simply yields false, leaving the gates to raise the appropriate error.
     */
    private function hasAllowingRole(
        ReflectionMethod|ReflectionFunction $ref,
        ServerRequestInterface $request
    ): bool {
        if (! $ref instanceof ReflectionMethod) {
            return false;
        }

        $allowedRoles = [];
        foreach ($ref->getDeclaringClass()->getAttributes(AllowsRole::class) as $allowsRoleAttr) {
            $allowedRoles[] = (string) $allowsRoleAttr->newInstance()->role->value;
        }

        if (empty($allowedRoles)) {
            return false;
        }

        $user = $request->getAttribute('user');
        if (! $user instanceof AuthenticatedUserInterface) {
            return false;
        }

        return ! empty(array_intersect($user->getRoles(), $allowedRoles));
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
