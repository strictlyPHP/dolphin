# Dolphin Framework

[![Coverage Status](https://coveralls.io/repos/github/strictlyPHP/dolphin/badge.svg?branch=main)](https://coveralls.io/github/strictlyPHP/dolphin?branch=main)
![CI Status](https://github.com/strictlyPHP/dolphin/actions/workflows/test-main.yml/badge.svg)
![Stable](https://img.shields.io/packagist/v/strictlyphp/dolphpin)

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

### Controller Example

A controller handles the logic of the request. Example:

```php
<?php

namespace Service\User\Controllers;

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

use Service\User\Controllers\CreateUserController;
use League\Route\Router;

function main(array $event, object $context): array
{
    $router = new Router;
    $router->post('/users', CreateUserController::class);
    
    $app = new \StrictlyPHP\Dolphin\App($router);
    return $app->run($event, $context);
}
```

## License

This project is licensed under the MIT License.
