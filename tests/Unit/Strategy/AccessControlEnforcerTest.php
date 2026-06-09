<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Strategy;

use League\Route\Http\Exception\ForbiddenException;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionMethod;
use Slim\Psr7\Factory\ServerRequestFactory;
use StrictlyPHP\Dolphin\Attributes\RequiresPermission;
use StrictlyPHP\Dolphin\Authentication\AuthenticatedUserInterface;
use StrictlyPHP\Dolphin\Authorization\AuthorizationServiceInterface;
use StrictlyPHP\Dolphin\Strategy\AccessControlEnforcer;
use StrictlyPHP\Tests\Dolphin\Fixtures\Authorization\TestPermission;
use StrictlyPHP\Tests\Dolphin\Fixtures\Authorization\UserType;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestAllowsRoleOnlyController;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestAllowsRoleOverRoleController;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestAllowsRolePermissionController;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestRequiresAnyPermissionController;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestRequiresPermissionController;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestRequiresSingleRoleController;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestUserAdmin;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestUserRegular;

class AccessControlEnforcerTest extends TestCase
{
    public function testFunctionHandlersPassThroughWithoutClassAttributeChecks(): void
    {
        $enforcer = new AccessControlEnforcer();
        $ref = new ReflectionFunction(static fn (): string => 'ok');
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/function-handler');

        $result = $enforcer->enforce($ref, $request);

        $this->assertSame([], $result->getAttribute('required_roles'));
        $this->assertSame([], $result->getAttribute('required_permissions'));
    }

    public function testItSetsRequiredPermissionsAttributeOnTheRequest(): void
    {
        $authorizationService = $this->createStub(AuthorizationServiceInterface::class);
        $authorizationService->method('isAllowed')->willReturn(true);

        $enforcer = new AccessControlEnforcer($authorizationService);
        $ref = new ReflectionMethod(TestRequiresPermissionController::class, '__invoke');
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/permission')
            ->withAttribute('user', new TestUserAdmin());

        $result = $enforcer->enforce($ref, $request);

        /** @var array<int, RequiresPermission> $requiredPermissions */
        $requiredPermissions = $result->getAttribute('required_permissions');
        $this->assertCount(1, $requiredPermissions);
        $this->assertInstanceOf(RequiresPermission::class, $requiredPermissions[0]);
        $this->assertSame(UserType::ADMIN, $requiredPermissions[0]->userKind);
        $this->assertSame(TestPermission::CREATE_USER, $requiredPermissions[0]->permission);
    }

    public function testItSetsAllRepeatedPermissionsOnTheRequest(): void
    {
        $authorizationService = $this->createStub(AuthorizationServiceInterface::class);
        $authorizationService->method('isAllowed')->willReturn(true);

        $enforcer = new AccessControlEnforcer($authorizationService);
        $ref = new ReflectionMethod(TestRequiresAnyPermissionController::class, '__invoke');
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/any-permission')
            ->withAttribute('user', new TestUserAdmin());

        $result = $enforcer->enforce($ref, $request);

        /** @var array<int, RequiresPermission> $requiredPermissions */
        $requiredPermissions = $result->getAttribute('required_permissions');
        $this->assertCount(2, $requiredPermissions);
        $this->assertSame(TestPermission::DELETE_USER, $requiredPermissions[0]->permission);
        $this->assertSame(TestPermission::CREATE_USER, $requiredPermissions[1]->permission);
    }

    public function testHandlersWithoutPermissionAttributesGetEmptyRequiredPermissions(): void
    {
        $controller = new class() {
            public function __invoke(): string
            {
                return 'ok';
            }
        };

        $enforcer = new AccessControlEnforcer();
        $ref = new ReflectionMethod($controller, '__invoke');
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/plain');

        $result = $enforcer->enforce($ref, $request);

        $this->assertSame([], $result->getAttribute('required_permissions'));
    }

    public function testRequiresRoleAttributesCombineIntoRequiredRoles(): void
    {
        $enforcer = new AccessControlEnforcer();
        $ref = new ReflectionMethod(TestRequiresSingleRoleController::class, '__invoke');
        $request = $this->requestWithUser('/requires-role', new TestUserAdmin());

        $result = $enforcer->enforce($ref, $request);

        $this->assertSame(['ADMIN', 'USER'], $result->getAttribute('required_roles'));
    }

    public function testRequiresRoleAllowsUserHoldingOneOfTheRoles(): void
    {
        $enforcer = new AccessControlEnforcer();
        $ref = new ReflectionMethod(TestRequiresSingleRoleController::class, '__invoke');
        // TestUserRegular holds USER, which is one of the OR-listed roles
        $request = $this->requestWithUser('/requires-role', new TestUserRegular());

        $result = $enforcer->enforce($ref, $request);

        $this->assertSame(['ADMIN', 'USER'], $result->getAttribute('required_roles'));
    }

    public function testRequiresRoleThrows403WhenUserHoldsNoneOfTheRoles(): void
    {
        $enforcer = new AccessControlEnforcer();
        $ref = new ReflectionMethod(TestRequiresSingleRoleController::class, '__invoke');
        $request = $this->requestWithUser('/requires-role', $this->userWithRoles(['GUEST']));

        $this->expectException(ForbiddenException::class);
        $enforcer->enforce($ref, $request);
    }

    public function testAllowsRoleBypassesPermissionGateWithoutCallingService(): void
    {
        $authorizationService = $this->createStub(AuthorizationServiceInterface::class);
        $authorizationService->method('isAllowed')->willReturn(false);

        $enforcer = new AccessControlEnforcer($authorizationService);
        $ref = new ReflectionMethod(TestAllowsRolePermissionController::class, '__invoke');
        // ADMIN is allowed straight through even though the permission is denied
        $request = $this->requestWithUser('/allows-role-permission', new TestUserAdmin());

        $result = $enforcer->enforce($ref, $request);

        $this->assertCount(1, $result->getAttribute('required_permissions'));
    }

    public function testAllowsRoleBypassesPermissionGateEvenWithNoServiceBound(): void
    {
        $enforcer = new AccessControlEnforcer();
        $ref = new ReflectionMethod(TestAllowsRolePermissionController::class, '__invoke');
        $request = $this->requestWithUser('/allows-role-permission', new TestUserAdmin());

        // Would throw RuntimeException without the bypass; ADMIN escapes the gate
        $result = $enforcer->enforce($ref, $request);

        $this->assertCount(1, $result->getAttribute('required_permissions'));
    }

    public function testPermissionGateStillEnforcedForUsersWithoutTheAllowedRole(): void
    {
        $authorizationService = $this->createStub(AuthorizationServiceInterface::class);
        $authorizationService->method('isAllowed')->willReturn(true);

        $enforcer = new AccessControlEnforcer($authorizationService);
        $ref = new ReflectionMethod(TestAllowsRolePermissionController::class, '__invoke');
        // USER lacks the ADMIN bypass, but the permission is granted
        $request = $this->requestWithUser('/allows-role-permission', new TestUserRegular());

        $result = $enforcer->enforce($ref, $request);

        $this->assertCount(1, $result->getAttribute('required_permissions'));
    }

    public function testPermissionGateDeniesUserWithoutAllowedRoleOrPermission(): void
    {
        $authorizationService = $this->createStub(AuthorizationServiceInterface::class);
        $authorizationService->method('isAllowed')->willReturn(false);

        $enforcer = new AccessControlEnforcer($authorizationService);
        $ref = new ReflectionMethod(TestAllowsRolePermissionController::class, '__invoke');
        $request = $this->requestWithUser('/allows-role-permission', new TestUserRegular());

        $this->expectException(ForbiddenException::class);
        $enforcer->enforce($ref, $request);
    }

    public function testAllowsRoleBypassesRoleGate(): void
    {
        $enforcer = new AccessControlEnforcer();
        $ref = new ReflectionMethod(TestAllowsRoleOverRoleController::class, '__invoke');
        // Route requires USER; ADMIN lacks it but is allowed through
        $request = $this->requestWithUser('/allows-role-over-role', new TestUserAdmin());

        $result = $enforcer->enforce($ref, $request);

        $this->assertSame(['USER'], $result->getAttribute('required_roles'));
    }

    public function testAllowsRoleAloneLeavesRouteOpen(): void
    {
        $enforcer = new AccessControlEnforcer();
        $ref = new ReflectionMethod(TestAllowsRoleOnlyController::class, '__invoke');
        // No user on the request, no gate declared — nothing to enforce
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/allows-role-only');

        $result = $enforcer->enforce($ref, $request);

        $this->assertSame([], $result->getAttribute('required_roles'));
        $this->assertSame([], $result->getAttribute('required_permissions'));
    }

    private function requestWithUser(string $path, AuthenticatedUserInterface $user): \Psr\Http\Message\ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest('POST', $path)
            ->withAttribute('user', $user);
    }

    /**
     * @param array<int, string> $roles
     */
    private function userWithRoles(array $roles): AuthenticatedUserInterface
    {
        return new class($roles) implements AuthenticatedUserInterface {
            /**
             * @param array<int, string> $roles
             */
            public function __construct(
                private array $roles
            ) {
            }

            public function getId(): string
            {
                return 'test-user';
            }

            public function getRoles(): array
            {
                return $this->roles;
            }
        };
    }
}
