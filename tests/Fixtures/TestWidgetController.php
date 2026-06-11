<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use StrictlyPHP\Dolphin\Attributes\RequiresRoles;
use StrictlyPHP\Dolphin\Attributes\Route;
use StrictlyPHP\Dolphin\Request\Method;
use StrictlyPHP\Dolphin\Response\JsonResponse;

#[Route(method: Method::GET, path: '/widgets/{id}')]
#[RequiresRoles(roles: ['USER'])]
class TestWidgetController
{
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'response' => 'widget',
            // Echo any attribute a route enforcer may have set, so tests can
            // assert the enriched request reached the controller.
            'enforced_by' => $request->getAttribute('enforced_by'),
        ]);
    }
}
