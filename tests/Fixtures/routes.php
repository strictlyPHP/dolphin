<?php

declare(strict_types=1);

use League\Route\Router;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestController;

return function (): RequestHandlerInterface {
    $router = new Router();
    $router->post('/test', TestController::class);
    return $router;
};
