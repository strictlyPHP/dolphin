<?php

declare(strict_types=1);
namespace StrictlyPHP\Tests\Dolphin\Integration;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Dolphin\App;
use StrictlyPHP\Dolphin\Authentication\AuthenticatedUserInterface;
use StrictlyPHP\Dolphin\Authorization\AuthorizationServiceInterface;
use StrictlyPHP\Dolphin\Authorization\PermissionInterface;
use StrictlyPHP\Dolphin\Authorization\RoleInterface;
use StrictlyPHP\Tests\Dolphin\Fixtures\InsertAdminUserMiddleware;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestRequiresPermissionController;

class AppPermissionTest extends TestCase
{
    public function testItAllowsWhenAuthorizationServiceAllows(): void
    {
        $app = App::build(
            controllers: [
                TestRequiresPermissionController::class,
            ],
            containerDefinitions: [
                AuthorizationServiceInterface::class => $this->createAuthorizationService(true),
            ],
            middlewares: [
                InsertAdminUserMiddleware::class,
            ]
        );

        $response = $app->run($this->createEvent(), $this->createContext());

        $expectedResponse = [
            'statusCode' => 200,
            'body' => '{"response":"permission granted"}',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ];
        self::assertSame($expectedResponse, $response);
    }

    public function testItReturns403WhenAuthorizationServiceDenies(): void
    {
        $app = App::build(
            controllers: [
                TestRequiresPermissionController::class,
            ],
            containerDefinitions: [
                AuthorizationServiceInterface::class => $this->createAuthorizationService(false),
            ],
            middlewares: [
                InsertAdminUserMiddleware::class,
            ]
        );

        $response = $app->run($this->createEvent(), $this->createContext());

        $expectedResponse = [
            'statusCode' => 403,
            'body' => '{"statusCode":403,"reasonPhrase":"User does not have permission to access this resource"}',
            'headers' => [
                'content-type' => 'application/json',
            ],
        ];
        self::assertSame($expectedResponse, $response);
    }

    public function testItReturns500WhenNoAuthorizationServiceIsBound(): void
    {
        $app = App::build(
            controllers: [
                TestRequiresPermissionController::class,
            ],
            middlewares: [
                InsertAdminUserMiddleware::class,
            ]
        );

        $response = $app->run($this->createEvent(), $this->createContext());

        self::assertSame(500, $response['statusCode']);
        self::assertSame(
            '{"statusCode":500,"reasonPhrase":"Internal Server Error"}',
            $response['body']
        );
    }

    private function createAuthorizationService(bool $allowed): AuthorizationServiceInterface
    {
        return new class($allowed) implements AuthorizationServiceInterface {
            public function __construct(
                private bool $allowed
            ) {
            }

            public function isAllowed(
                AuthenticatedUserInterface $user,
                RoleInterface $userKind,
                PermissionInterface $permission,
            ): bool {
                return $this->allowed;
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function createEvent(): array
    {
        return [
            'http' => [
                'path' => '/permission',
                'body' => '{}',
                'isBase64Encoded' => false,
                'queryString' => '',
                'method' => 'POST',
                'headers' => [
                    'accept' => '*/*',
                    'content-type' => 'application/json',
                ],
            ],
        ];
    }

    private function createContext(): object
    {
        return new class() {
            public string $apiHost = 'https://faas-lon1-917a94a7.doserverless.co';

            public function getRemainingTimeInMillis(): int
            {
                return 1000;
            }
        };
    }
}
