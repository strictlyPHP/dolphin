<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use StrictlyPHP\Dolphin\Attributes\AllowsRole;
use StrictlyPHP\Dolphin\Attributes\RequiresRole;
use StrictlyPHP\Dolphin\Attributes\Route;
use StrictlyPHP\Dolphin\Request\Method;
use StrictlyPHP\Dolphin\Response\JsonResponse;
use StrictlyPHP\Tests\Dolphin\Fixtures\Authorization\UserType;

#[Route(method: Method::POST, path: '/allows-role-over-role')]
#[RequiresRole(UserType::USER)]
#[AllowsRole(UserType::ADMIN)]
class TestAllowsRoleOverRoleController
{
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'response' => 'access granted',
        ]);
    }
}
