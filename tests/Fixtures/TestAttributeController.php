<?php

declare(strict_types=1);
namespace StrictlyPHP\Tests\Dolphin\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use StrictlyPHP\Dolphin\Attributes\Route;
use StrictlyPHP\Dolphin\Request\Method;
use StrictlyPHP\Dolphin\Response\JsonResponse;

#[Route(Method::POST, '/test')]
class TestAttributeController
{
    public function __construct(
        private TestService $testService,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->testService->run($request->getBody()->getContents());
        return new JsonResponse([
            'user' => $response,
        ]);
    }
}
