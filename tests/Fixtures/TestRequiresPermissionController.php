<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use StrictlyPHP\Dolphin\Attributes\RequiresPermission;
use StrictlyPHP\Dolphin\Attributes\Route;
use StrictlyPHP\Dolphin\Request\Method;
use StrictlyPHP\Dolphin\Response\JsonResponse;
use StrictlyPHP\Tests\Dolphin\Fixtures\Authorization\TestPermission;
use StrictlyPHP\Tests\Dolphin\Fixtures\Authorization\UserType;

#[Route(method: Method::POST, path: '/permission')]
#[RequiresPermission(userKind: UserType::ADMIN, permission: TestPermission::CREATE_USER)]
class TestRequiresPermissionController
{
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'response' => 'permission granted',
        ]);
    }
}
