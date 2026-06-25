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
use Psr\Http\Server\RequestHandlerInterface;
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
use StrictlyPHP\Tests\Dolphin\Fixtures\TestLogger;
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

    public function testSuccessPathDegradesMalformedUtf8InsteadOf500(): void
    {
        $controller = new class() {
            /**
             * @return array<string, string>
             */
            public function __invoke(ServerRequestInterface $request): array
            {
                return [
                    'response' => "bad\xB1byte",
                ];
            }
        };

        $strategy = $this->createStrategy(null);
        $route = new Route('GET', '/legacy', $controller);

        $response = $strategy->invokeRouteCallable($route, $this->createRequest());

        $this->assertSame(200, $response->getStatusCode());

        $decoded = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($decoded);
        $this->assertSame("bad\u{FFFD}byte", $decoded['response']);
    }

    public function testErrorHandlerNeverMasksRealErrorWithUnencodablePayload(): void
    {
        $logger = new TestLogger();
        $strategy = new DolphinAppStrategy(
            dtoMapper: new DtoMapper(),
            responseFactory: new ResponseFactory(),
            logger: $logger,
            debugMode: true,
        );

        // A message containing malformed UTF-8 would make json_encode() return false
        // on the old code path, producing a write(false) TypeError that masks this
        // very exception.
        $realException = new \RuntimeException("missing table \xB1 recruiter_jobs");

        $handler = new class($realException) implements RequestHandlerInterface {
            public function __construct(
                private \Throwable $exception
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw $this->exception;
            }
        };

        $response = $strategy->getThrowableHandler()->process($this->createRequest(), $handler);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('content-type'));

        $decoded = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($decoded);
        $this->assertSame(500, $decoded['statusCode']);
        // The real, logged error survives into the response (bad byte substituted).
        $this->assertStringContainsString('missing table', $decoded['exception']['message']);
        $this->assertStringContainsString('recruiter_jobs', $decoded['exception']['message']);
        // The trace is a plain string, not an array of frame args.
        $this->assertIsString($decoded['exception']['trace']);

        $this->assertSame('critical', $logger->getLogs()[0]['level']);
    }

    public function testErrorHandlerEncodesTraceThroughClosureAsString(): void
    {
        $strategy = new DolphinAppStrategy(
            dtoMapper: new DtoMapper(),
            responseFactory: new ResponseFactory(),
            debugMode: true,
        );

        // Build an exception whose stack trace passes an object and a closure as
        // call arguments — getTrace() frame args that are not json-encodable.
        $handler = new class() implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $thrower = static function (object $obj, \Closure $fn): void {
                    throw new \RuntimeException('boom through closure');
                };

                $thrower(new \stdClass(), static fn (): bool => true);
            }
        };

        $response = $strategy->getThrowableHandler()->process($this->createRequest(), $handler);

        $this->assertSame(500, $response->getStatusCode());

        $decoded = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($decoded);
        $this->assertSame('boom through closure', $decoded['exception']['message']);
        $this->assertIsString($decoded['exception']['trace']);
    }

    public function testNonDebugErrorHandlerReturnsCleanJsonWithoutTrace(): void
    {
        $strategy = new DolphinAppStrategy(
            dtoMapper: new DtoMapper(),
            responseFactory: new ResponseFactory(),
            debugMode: false,
        );

        $handler = new class() implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('something broke');
            }
        };

        $response = $strategy->getThrowableHandler()->process($this->createRequest(), $handler);

        $this->assertSame(500, $response->getStatusCode());

        $decoded = json_decode((string) $response->getBody(), true);
        $this->assertSame([
            'statusCode' => 500,
            'reasonPhrase' => 'Internal Server Error',
        ], $decoded);
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
