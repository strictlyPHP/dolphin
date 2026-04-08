<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Factory\ResponseFactory;
use StrictlyPHP\Dolphin\Strategy\DolphinAppStrategy;
use StrictlyPHP\Dolphin\Strategy\DtoMapper;

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
}
