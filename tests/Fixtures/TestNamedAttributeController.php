<?php

declare(strict_types=1);
namespace StrictlyPHP\Tests\Dolphin\Fixtures;

use League\Route\Http\Exception\UnauthorizedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use StrictlyPHP\Dolphin\Attributes\Route;
use StrictlyPHP\Dolphin\Request\Method;
use StrictlyPHP\Dolphin\Response\JsonResponse;

#[Route(method: Method::POST, path: '/test')]
class TestNamedAttributeController
{
    public function __construct(
        private TestService $testService,
    ) {
    }

    public function __invoke(TestRequestDto $testDto, ServerRequestInterface $request, array $vars): ResponseInterface
    {
        if ($testDto->getUsername() === 'not-authorised') {
            throw new UnauthorizedException('Forced not authorised error');
        }
        $response = $this->testService->run(
            $testDto->getUsername(),
            $testDto->getEmail(),
            $testDto->getName()
        );
        return new JsonResponse([
            'response' => $response,
            'vars' => $vars,
            'requestBody' => (string) $request->getBody(),
        ]);
    }
}
