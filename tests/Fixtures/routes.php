<?php

declare(strict_types=1);

use League\Route\Router;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestController;

$router = new Router();
$router->post('/test', TestController::class);
