# Dolphin Framework

[![Coverage Status](https://coveralls.io/repos/github/strictlyPHP/dolphin/badge.svg?branch=main)](https://coveralls.io/github/strictlyPHP/dolphin?branch=main)
![CI Status](https://github.com/strictlyPHP/dolphin/actions/workflows/test-main.yml/badge.svg)
![Stable](https://img.shields.io/packagist/v/strictlyphp/dolphpin)

Dolphin is a lightweight PHP framework designed for running serverless functions on DigitalOcean. It provides attribute-based routing, automatic DTO mapping, role-based access control, and dependency injection out of the box.

For a detailed look at the internals, see [ARCHITECTURE.md](ARCHITECTURE.md).

## Requirements

- PHP >= 8.2
- Extensions: intl, bcmath, simplexml, curl, mbstring

## Installation

```bash
composer require strictlyphp/dolphin:^3.0
```

## Quick Start

### 1. Define a Controller

Controllers are invokable classes annotated with `#[Route]`. The framework automatically deserializes the JSON request body into typed DTOs:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use StrictlyPHP\Dolphin\Attributes\Route;
use StrictlyPHP\Dolphin\Request\Method;
use StrictlyPHP\Dolphin\Response\JsonResponse;

#[Route(Method::POST, '/users')]
class CreateUserController
{
    public function __invoke(CreateUserDto $dto, ServerRequestInterface $request): ResponseInterface
    {
        // $dto is automatically mapped from the JSON body
        return new JsonResponse(['id' => '123', 'name' => $dto->name], 201);
    }
}
```

### 2. Define a DTO

DTOs are plain readonly classes. The framework maps JSON fields to constructor parameters, supporting scalars, value objects, nested DTOs, backed enums, and typed arrays:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

readonly class CreateUserDto
{
    public function __construct(
        public string $name,
        public EmailAddress $email,
    ) {
    }
}
```

### 3. Bootstrap the Application

Use `App::build()` in your DigitalOcean function entry point. Pass the namespace(s) containing your controllers — routes are discovered automatically from `#[Route]` attributes:

```php
<?php

use StrictlyPHP\Dolphin\App;

function main(array $event, object $context): array
{
    $app = App::build(
        controllers: ['App\Controllers'],
    );

    return $app->run($event, $context);
}
```

## Features

### Attribute-Based Routing

Routes are declared directly on controller classes using `#[Route]`:

```php
#[Route(Method::GET, '/users/{id}')]
class GetUserController { /* ... */ }

#[Route(Method::DELETE, '/users/{id}')]
class DeleteUserController { /* ... */ }
```

Supported HTTP methods: `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `HEAD`.

### Automatic DTO Mapping

Controller parameters that are class types are automatically deserialized from the JSON request body. The mapper supports:

- **Scalar types** — `string`, `int`, `float`, `bool`
- **Value objects** — Single-constructor-argument classes (e.g. `new EmailAddress($value)`)
- **Nested DTOs** — Recursively mapped from nested JSON objects
- **Backed enums** — Resolved via `::tryFrom()`
- **Typed arrays** — Element type declared via `@param array<Type>` docblock annotations
- **Nullable parameters** — Mapped to `null` when absent

### Role-Based Access Control

Protect controllers with `#[RequiresRoles]`. The framework checks the authenticated user's roles before invoking the controller:

```php
#[Route(Method::POST, '/admin/settings')]
#[RequiresRoles(['ADMIN'])]
class UpdateSettingsController { /* ... */ }
```

This requires middleware that sets a `user` attribute on the request implementing `AuthenticatedUserInterface`:

```php
use StrictlyPHP\Dolphin\Authentication\AuthenticatedUserInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = // ... resolve authenticated user
        $request = $request->withAttribute('user', $user);
        return $handler->handle($request);
    }
}
```

The `AuthenticatedUserInterface` requires `getId(): string` and `getRoles(): array`.

### Dependency Injection

Dolphin uses [PHP-DI](https://php-di.org/) for dependency injection. Pass container definitions to `App::build()`:

```php
$app = App::build(
    controllers: ['App\Controllers'],
    containerDefinitions: [
        UserRepository::class => fn() => new UserRepository($db),
    ],
);
```

Controllers are resolved through the container, so constructor dependencies are injected automatically.

### Middleware

Register PSR-15 middleware globally via `App::build()`:

```php
$app = App::build(
    controllers: ['App\Controllers'],
    middlewares: [AuthMiddleware::class, CorsMiddleware::class],
);
```

### Debug Mode

Enable debug mode to include exception details (message, request body, stack trace) in error responses:

```php
$app = App::build(
    controllers: ['App\Controllers'],
    debugMode: true,
);
```

### Custom Throwable Handler

By default, Dolphin catches all exceptions and returns JSON error responses with appropriate status codes. You can provide your own throwable handler middleware to customize this behavior:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CustomErrorHandler implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            // Your custom error handling logic
            $response = (new \Slim\Psr7\Factory\ResponseFactory())->createResponse(500);
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }
}

$app = App::build(
    controllers: ['App\Controllers'],
    throwableHandler: new CustomErrorHandler(),
);
```

Custom handlers are PSR-15 middleware implementing `MiddlewareInterface`. They are responsible for their own logging, error formatting, and configuration.

### JSON Responses

Use `JsonResponse` for convenience:

```php
use StrictlyPHP\Dolphin\Response\JsonResponse;

return new JsonResponse(['key' => 'value']);           // 200
return new JsonResponse(['created' => true], 201);     // 201
```

## Development

The project uses Docker for a consistent development environment. Available Make commands:

```bash
make install         # Install dependencies
make analyze         # Run PHPStan static analysis (level 6)
make style           # Check coding style (ECS / PSR-12)
make style-fix       # Auto-fix coding style issues
make coveralls       # Run tests with coverage
make check-coverage  # Check test coverage of changed files
```

## License

This project is licensed under the MIT License.
