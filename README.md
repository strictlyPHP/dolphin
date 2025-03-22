# Dolphin Framework

Dolphin is a lightweight PHP framework designed for running DigitalOcean functions. It has two versions:

- **v1**: For PHP 8
- **v2**: For PHP 8.2

## Installation

Install Dolphin via Composer:

```bash
composer require strictlyphp/dolphin:^1.0  # For PHP 8
composer require strictlyphp/dolphin:^2.0  # For PHP 8.2
```

## Usage

### Routes File

Create a `routes.php` file for routing configuration. Here's an example:

```php
<?php

use Service\User\Controllers\CreateUserController;
use League\Route\Router;

$router = new Router;
$router->post('/users', CreateUserController::class);
```

### Controller Example

A controller handles the logic of the request. Example:

```php
<?php

namespace Service\User\Controllers;

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use StrictlyPHP\Dolphin\Response\JsonResponse;

class CreateUserController
{
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(['foo' => 'bar']);
    }
}
```

### Main Index File

In your main application file, you will include and run the app using the `routes.php`:

```php
<?php

function main(array $event, object $context): array
{
    $app = new \StrictlyPHP\Dolphin\App(__DIR__.'/routes.php');
    return $app->run($event, $context);
}
```

## License

This project is licensed under the MIT License.
```

This markdown version includes installation instructions, usage details (routes and controllers), and the main application setup. It is a comprehensive README for the Dolphin framework.