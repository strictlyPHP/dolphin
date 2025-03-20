<?php

declare(strict_types=1);
namespace StrictlyPHP\Tests\Dolphin\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use StrictlyPHP\Dolphin\Response\JsonResponse;

class TestController
{
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'requestBody' => $request->getBody()->getContents(),
        ]);
    }
}
