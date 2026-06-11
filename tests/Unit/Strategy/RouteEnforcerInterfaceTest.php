<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Strategy;

use League\Route\Http\Exception\ForbiddenException;
use League\Route\Route;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunction;
use ReflectionMethod;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use StrictlyPHP\Dolphin\Strategy\DolphinAppStrategy;
use StrictlyPHP\Dolphin\Strategy\DtoMapper;
use StrictlyPHP\Dolphin\Strategy\RouteEnforcerInterface;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestUserAdmin;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestUserRegular;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestWidgetController;

class RouteEnforcerInterfaceTest extends TestCase
{
    public function testZeroEnforcersBehavesLikeToday(): void
    {
        $strategy = $this->createStrategy([]);

        $response = $strategy->invokeRouteCallable($this->createRoute(), $this->createAuthenticatedRequest());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"response":"widget","enforced_by":null}', (string) $response->getBody());
    }

    public function testSingleEnforcerIsCalledWithRefRequestAndVars(): void
    {
        $captured = [];

        $enforcer = $this->createStub(RouteEnforcerInterface::class);
        $enforcer->method('enforce')->willReturnCallback(
            static function (
                ReflectionMethod|ReflectionFunction $ref,
                ServerRequestInterface $request,
                array $vars
            ) use (&$captured): ServerRequestInterface {
                $captured = [
                    'ref' => $ref,
                    'vars' => $vars,
                ];

                return $request;
            }
        );

        $strategy = $this->createStrategy([$enforcer]);
        $route = $this->createRoute();
        $route->setVars([
            'id' => '42',
        ]);

        $strategy->invokeRouteCallable($route, $this->createAuthenticatedRequest());

        $this->assertInstanceOf(ReflectionMethod::class, $captured['ref']);
        $this->assertSame(TestWidgetController::class, $captured['ref']->getDeclaringClass()->getName());
        $this->assertSame([
            'id' => '42',
        ], $captured['vars']);
    }

    public function testMultipleEnforcersRunInRegistrationOrderAndThreadTheRequest(): void
    {
        // Enforcer A appends 'a' to the attribute; B appends 'b'. The order of
        // the final value proves A ran before B and that B saw A's request.
        $append = static fn (string $tag): callable => static function (
            ReflectionMethod|ReflectionFunction $ref,
            ServerRequestInterface $request,
            array $vars
        ) use ($tag): ServerRequestInterface {
            $current = (string) $request->getAttribute('enforced_by');

            return $request->withAttribute('enforced_by', $current . $tag);
        };

        $enforcerA = $this->createStub(RouteEnforcerInterface::class);
        $enforcerA->method('enforce')->willReturnCallback($append('a'));

        $enforcerB = $this->createStub(RouteEnforcerInterface::class);
        $enforcerB->method('enforce')->willReturnCallback($append('b'));

        $strategy = $this->createStrategy([$enforcerA, $enforcerB]);

        $response = $strategy->invokeRouteCallable($this->createRoute(), $this->createAuthenticatedRequest());

        $this->assertSame('{"response":"widget","enforced_by":"ab"}', (string) $response->getBody());
    }

    public function testThrowingEnforcerHaltsChainBeforeSubsequentEnforcersAndController(): void
    {
        $thrower = $this->createStub(RouteEnforcerInterface::class);
        $thrower->method('enforce')->willThrowException(new ForbiddenException('Policy violation'));

        $shouldNotRun = $this->createStub(RouteEnforcerInterface::class);
        $shouldNotRun->method('enforce')->willReturnCallback(
            function (): ServerRequestInterface {
                $this->fail('Enforcer after a throwing enforcer must not run');
            }
        );

        $strategy = $this->createStrategy([$thrower, $shouldNotRun]);

        $this->expectException(ForbiddenException::class);
        $strategy->invokeRouteCallable($this->createRoute(), $this->createAuthenticatedRequest());
    }

    public function testBuiltInAccessControlRunsBeforeAdditionalEnforcers(): void
    {
        // The user lacks the required USER role, so AccessControlEnforcer must
        // reject before the custom enforcer is ever consulted.
        $shouldNotRun = $this->createStub(RouteEnforcerInterface::class);
        $shouldNotRun->method('enforce')->willReturnCallback(
            function (): ServerRequestInterface {
                $this->fail('Custom enforcer must not run when the role gate fails');
            }
        );

        $strategy = $this->createStrategy([$shouldNotRun]);
        // TestUserAdmin holds ['ADMIN'] — no intersection with the required ['USER'].
        $request = $this->createRequest()->withAttribute('user', new TestUserAdmin());

        $this->expectException(ForbiddenException::class);
        $strategy->invokeRouteCallable($this->createRoute(), $request);
    }

    /**
     * @param RouteEnforcerInterface[] $routeEnforcers
     */
    private function createStrategy(array $routeEnforcers): DolphinAppStrategy
    {
        return new DolphinAppStrategy(
            dtoMapper: new DtoMapper(),
            responseFactory: new ResponseFactory(),
            routeEnforcers: $routeEnforcers
        );
    }

    private function createRoute(): Route
    {
        return new Route('GET', '/widgets/{id}', new TestWidgetController());
    }

    private function createRequest(): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest('GET', '/widgets/42');
    }

    private function createAuthenticatedRequest(): ServerRequestInterface
    {
        return $this->createRequest()->withAttribute('user', new TestUserRegular());
    }
}
