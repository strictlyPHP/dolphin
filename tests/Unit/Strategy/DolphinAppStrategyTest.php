<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Strategy;

use League\Route\Http\Exception\ForbiddenException;
use League\Route\Http\Exception\UnauthorizedException;
use League\Route\Route;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use StrictlyPHP\Dolphin\Authentication\AuthenticatedUserInterface;
use StrictlyPHP\Dolphin\Authorization\AuthorizationServiceInterface;
use StrictlyPHP\Dolphin\Authorization\PermissionInterface;
use StrictlyPHP\Dolphin\Authorization\RoleInterface;
use StrictlyPHP\Dolphin\Response\JsonResponse;
use StrictlyPHP\Dolphin\Strategy\DolphinAppStrategy;
use StrictlyPHP\Dolphin\Strategy\DtoMapper;
use StrictlyPHP\Tests\Dolphin\Fixtures\Authorization\TestPermission;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestRequiresAnyPermissionController;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestRequiresPermissionController;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestUserAdmin;

class DolphinAppStrategyTest extends TestCase
{
    public function testGetThrowableHandlerReturnsCustomHandler(): void
    {
        $customHandler = $this->createMock(MiddlewareInterface::class);

        $strategy = new DolphinAppStrategy(
            dtoMapper: new DtoMapper(),
            responseFactory: new ResponseFactory(),
            throwableHandler: $customHandler
        );

        $this->assertSame($customHandler, $strategy->getThrowableHandler());
    }

    public function testGetThrowableHandlerReturnsDefaultWhenNoCustomHandler(): void
    {
        $strategy = new DolphinAppStrategy(
            dtoMapper: new DtoMapper(),
            responseFactory: new ResponseFactory(),
        );

        $handler = $strategy->getThrowableHandler();

        $this->assertInstanceOf(MiddlewareInterface::class, $handler);
        $this->assertNotSame($handler, $strategy->getThrowableHandler());
    }

    public function testRequiresPermissionAllowsWhenAuthorizationServiceAllows(): void
    {
        $authorizationService = $this->createStub(AuthorizationServiceInterface::class);
        $authorizationService->method('isAllowed')->willReturn(true);

        $strategy = $this->createStrategy($authorizationService);
        $route = new Route('POST', '/permission', new TestRequiresPermissionController());

        $response = $strategy->invokeRouteCallable($route, $this->createAuthenticatedRequest());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"response":"permission granted"}', (string) $response->getBody());
    }

    public function testRequiresPermissionThrows403WhenAuthorizationServiceDenies(): void
    {
        $authorizationService = $this->createStub(AuthorizationServiceInterface::class);
        $authorizationService->method('isAllowed')->willReturn(false);

        $strategy = $this->createStrategy($authorizationService);
        $route = new Route('POST', '/permission', new TestRequiresPermissionController());

        $this->expectException(ForbiddenException::class);
        $strategy->invokeRouteCallable($route, $this->createAuthenticatedRequest());
    }

    public function testRepeatedRequiresPermissionAllowsWhenAnyPermissionAllows(): void
    {
        $authorizationService = $this->createStub(AuthorizationServiceInterface::class);
        $authorizationService->method('isAllowed')->willReturnCallback(
            static fn (
                AuthenticatedUserInterface $user,
                RoleInterface $userKind,
                PermissionInterface $permission
            ): bool => $permission === TestPermission::CREATE_USER
        );

        $strategy = $this->createStrategy($authorizationService);
        // Declares DELETE_USER (denied) and CREATE_USER (allowed) — ANY-of passes
        $route = new Route('POST', '/any-permission', new TestRequiresAnyPermissionController());

        $response = $strategy->invokeRouteCallable($route, $this->createAuthenticatedRequest());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRepeatedRequiresPermissionThrows403WhenAllPermissionsDeny(): void
    {
        $authorizationService = $this->createStub(AuthorizationServiceInterface::class);
        $authorizationService->method('isAllowed')->willReturn(false);

        $strategy = $this->createStrategy($authorizationService);
        $route = new Route('POST', '/any-permission', new TestRequiresAnyPermissionController());

        $this->expectException(ForbiddenException::class);
        $strategy->invokeRouteCallable($route, $this->createAuthenticatedRequest());
    }

    public function testRequiresPermissionThrowsRuntimeExceptionWhenNoAuthorizationServiceBound(): void
    {
        $strategy = $this->createStrategy(null);
        $route = new Route('POST', '/permission', new TestRequiresPermissionController());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no AuthorizationServiceInterface is bound');
        $strategy->invokeRouteCallable($route, $this->createAuthenticatedRequest());
    }

    public function testRequiresPermissionThrows401WhenNoUserOnRequest(): void
    {
        $authorizationService = $this->createStub(AuthorizationServiceInterface::class);
        $authorizationService->method('isAllowed')->willReturn(true);

        $strategy = $this->createStrategy($authorizationService);
        $route = new Route('POST', '/permission', new TestRequiresPermissionController());

        $this->expectException(UnauthorizedException::class);
        $strategy->invokeRouteCallable($route, $this->createRequest());
    }

    public function testRequiresPermissionThrowsRuntimeExceptionWhenUserIsWrongType(): void
    {
        $authorizationService = $this->createStub(AuthorizationServiceInterface::class);
        $authorizationService->method('isAllowed')->willReturn(true);

        $strategy = $this->createStrategy($authorizationService);
        $route = new Route('POST', '/permission', new TestRequiresPermissionController());
        $request = $this->createRequest()->withAttribute('user', new \stdClass());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(AuthenticatedUserInterface::class);
        $strategy->invokeRouteCallable($route, $request);
    }

    public function testStrategyWithoutAuthorizationServiceStillHandlesRoutesWithoutPermissionAttribute(): void
    {
        $controller = new class() {
            public function __invoke(ServerRequestInterface $request): ResponseInterface
            {
                return new JsonResponse([
                    'response' => 'ok',
                ]);
            }
        };

        $strategy = $this->createStrategy(null);
        $route = new Route('POST', '/plain', $controller);

        $response = $strategy->invokeRouteCallable($route, $this->createRequest());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"response":"ok"}', (string) $response->getBody());
    }

    private function createStrategy(?AuthorizationServiceInterface $authorizationService): DolphinAppStrategy
    {
        return new DolphinAppStrategy(
            dtoMapper: new DtoMapper(),
            responseFactory: new ResponseFactory(),
            authorizationService: $authorizationService
        );
    }

    private function createRequest(): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest('POST', '/permission');
    }

    private function createAuthenticatedRequest(): ServerRequestInterface
    {
        return $this->createRequest()->withAttribute('user', new TestUserAdmin());
    }
}
