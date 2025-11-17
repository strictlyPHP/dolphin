<?php

declare(strict_types=1);
namespace StrictlyPHP\Tests\Dolphin\Fixtures;

use League\Route\Http\Exception\UnauthorizedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use StrictlyPHP\Dolphin\Attributes\Route;
use StrictlyPHP\Dolphin\Request\Method;
use StrictlyPHP\Dolphin\Response\JsonResponse;
use StrictlyPHP\Tests\Dolphin\Fixtures\Request\TestRequestDto;

#[Route(Method::POST, '/test')]
class TestAttributeController
{
    public function __construct(
        private TestService $testService,
    ) {
    }

    /**
     * @param array<int, string> $vars
     */
    public function __invoke(TestRequestDto $testDto, ServerRequestInterface $request, array $vars): ResponseInterface
    {
        if ($testDto->username === 'not-authorised') {
            throw new UnauthorizedException('Forced not authorised error');
        }
        $response = $this->testService->run(
            $testDto->username,
            $testDto->email,
            $testDto->name
        );
        return new JsonResponse([
            'response' => $response,
            'vars' => $vars,
            'requestBody' => (string) $request->getBody(),
        ]);
    }
}
