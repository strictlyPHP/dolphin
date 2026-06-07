<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionMethod;
use Slim\Psr7\Factory\ServerRequestFactory;
use StrictlyPHP\Dolphin\Attributes\RequiresPermission;
use StrictlyPHP\Dolphin\Authorization\AuthorizationServiceInterface;
use StrictlyPHP\Dolphin\Strategy\AccessControlEnforcer;
use StrictlyPHP\Tests\Dolphin\Fixtures\Authorization\TestPermission;
use StrictlyPHP\Tests\Dolphin\Fixtures\Authorization\UserType;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestRequiresAnyPermissionController;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestRequiresPermissionController;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestUserAdmin;

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
}
