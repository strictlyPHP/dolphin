<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use Slim\Psr7\Factory\ServerRequestFactory;
use StrictlyPHP\Dolphin\Strategy\AccessControlEnforcer;

class AccessControlEnforcerTest extends TestCase
{
    public function testFunctionHandlersPassThroughWithoutClassAttributeChecks(): void
    {
        $enforcer = new AccessControlEnforcer();
        $ref = new ReflectionFunction(static fn (): string => 'ok');
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/function-handler');

        $result = $enforcer->enforce($ref, $request);

        $this->assertSame([], $result->getAttribute('required_roles'));
    }
}
