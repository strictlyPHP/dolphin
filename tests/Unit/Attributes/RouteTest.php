<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Attributes;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Dolphin\Attributes\Route;
use StrictlyPHP\Dolphin\Request\Method;

class RouteTest extends TestCase
{
    public function testRouteAttribute(): void
    {
        $route = new Route(
            Method::POST,
            '/test'
        );

        $this->assertSame(Method::POST, $route->method);
        $this->assertSame('/test', $route->path);
    }
}
