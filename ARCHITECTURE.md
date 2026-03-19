# Architecture

This document describes the internal architecture of the Dolphin Framework.

## Overview

Dolphin is built on top of [League Route](https://route.thephpleague.com/) and [PHP-DI](https://php-di.org/), extending them with attribute-based routing, automatic DTO mapping, and role-based access control. It converts DigitalOcean function events into PSR-7 requests, routes them to controllers, and returns structured JSON responses.

## Directory Structure

```
src/
├── App.php                          # Application bootstrapper and entry point
├── Attributes/
│   ├── Route.php                    # #[Route] attribute for declaring HTTP routes
│   └── RequiresRoles.php           # #[RequiresRoles] attribute for RBAC
├── Authentication/
│   └── AuthenticatedUserInterface.php  # Contract for authenticated users
├── Request/
│   └── Method.php                   # HTTP method enum (GET, POST, PUT, etc.)
├── Response/
│   └── JsonResponse.php             # Convenience class for JSON responses
└── Strategy/
    ├── DolphinAppStrategy.php       # Custom routing strategy (DI, DTO mapping, RBAC)
    ├── DtoMapper.php                # Reflection-based JSON-to-DTO mapper
    └── Exception/
        ├── DtoMapperException.php
        └── ArrayTypeNotDeclaredException.php
```

## Request Lifecycle

```
DigitalOcean Event
        │
        ▼
   App::run()
   ┌─────────────────────────┐
   │ Convert event to PSR-7  │
   │ Request (Slim PSR-7)    │
   └────────┬────────────────┘
            ▼
   League Router
   ┌─────────────────────────┐
   │ Match route via #[Route]│
   │ attributes              │
   └────────┬────────────────┘
            ▼
   Global Middleware Pipeline
   ┌─────────────────────────┐
   │ PSR-15 middleware stack  │
   │ (e.g. auth middleware)  │
   └────────┬────────────────┘
            ▼
   DolphinAppStrategy::invokeRouteCallable()
   ┌─────────────────────────┐
   │ 1. Check #[RequiresRoles]│
   │ 2. Resolve parameters   │
   │ 3. Map JSON → DTO       │
   │ 4. Inject dependencies  │
   │ 5. Call controller       │
   └────────┬────────────────┘
            ▼
   Controller::__invoke()
   ┌─────────────────────────┐
   │ Business logic           │
   │ Returns ResponseInterface│
   └────────┬────────────────┘
            ▼
   JSON Response → DigitalOcean
```

## Key Components

### App (`App.php`)

The central class with two responsibilities:

- **`App::build()`** — Static factory that wires everything together:
  1. Creates a PHP-DI container with user-provided definitions
  2. Sets up a Monolog logger (or uses one from the container)
  3. Configures the `DolphinAppStrategy` on a League Router
  4. Auto-discovers controllers by scanning namespaces using `ClassFinder`
  5. Reads `#[Route]` attributes from discovered classes and registers them

- **`App::run()`** — Converts a DigitalOcean function event/context pair into a PSR-7 `Request`, dispatches it through the router, and returns the response as an array (`statusCode`, `body`, `headers`).

### DolphinAppStrategy (`Strategy/DolphinAppStrategy.php`)

Extends League Route's `JsonStrategy` to add:

- **Role-based access control** — Reads `#[RequiresRoles]` attributes from the matched controller class. If roles are required, it looks for an `AuthenticatedUserInterface` on the request's `user` attribute (typically set by middleware). Throws `401 Unauthorized` if no user is present, or `403 Forbidden` if the user lacks the required roles.

- **Automatic parameter injection** — Inspects the controller's `__invoke` parameters via reflection and injects:
  - `ServerRequestInterface` — the PSR-7 request
  - `array` — route variables
  - Any class type — deserialized from the JSON request body via `DtoMapper`

- **Error handling** — Catches exceptions and returns structured JSON error responses. In debug mode, includes the exception message, request body, and stack trace.

### DtoMapper (`Strategy/DtoMapper.php`)

Maps raw JSON arrays to strongly-typed PHP objects using reflection. Supports:

| Type | Mapping behaviour |
|------|-------------------|
| **Scalars** (`string`, `int`, `float`, `bool`) | Passed through directly |
| **Value objects** (single-constructor-arg classes) | Instantiated with the raw value, e.g. `new EmailAddress($value)` |
| **Nested DTOs** (multi-field classes) | Recursively mapped from nested arrays |
| **Backed enums** | Resolved via `::tryFrom()` |
| **Typed arrays** | Element type read from `@param array<Type>` docblock annotations |
| **Nullable parameters** | Mapped to `null` when absent or explicitly null |

Class name resolution for array element types uses PHP-Parser to read `use` statements from the DTO's source file, with results cached per class.

### Attributes

- **`#[Route(Method $method, string $path)]`** — Declares the HTTP method and path a controller handles. Applied at the class level.

- **`#[RequiresRoles(array $roles)]`** — Declares which roles are required to access a controller. The strategy checks the user's roles (via `AuthenticatedUserInterface`) against these before invoking the controller.

### Authentication

`AuthenticatedUserInterface` defines the contract for user objects used in RBAC:

```php
interface AuthenticatedUserInterface
{
    public function getId(): string;
    public function getRoles(): array;
}
```

Middleware is responsible for resolving the authenticated user and attaching it to the request as the `user` attribute.

### Response

`JsonResponse` extends Slim's PSR-7 `Response` for convenience — pass an array or `JsonSerializable` and an optional status code:

```php
return new JsonResponse(['key' => 'value'], 201);
```

## Design Decisions

- **Attribute-based routing over configuration files** — Routes are declared on controller classes, keeping routing co-located with handler logic. `App::build()` discovers them automatically via namespace scanning.

- **Strategy pattern for request handling** — The custom `DolphinAppStrategy` plugs into League Route's strategy system, adding DTO mapping and RBAC without modifying the router itself.

- **Reflection-based DTO mapping** — Controllers declare typed DTOs as parameters and receive fully hydrated objects. No manual deserialization code or form request classes needed.

- **PSR compliance** — PSR-7 (HTTP messages), PSR-11 (container), PSR-15 (middleware), and PSR-3 (logging) are used throughout, keeping the framework interoperable with the broader PHP ecosystem.
