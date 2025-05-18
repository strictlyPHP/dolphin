<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin;

use DI\Container;
use HaydenPierce\ClassFinder\ClassFinder;
use League\Route\Router;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\UriFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use StrictlyPHP\Dolphin\Attributes\Route;
use StrictlyPHP\Dolphin\Request\Method;
use StrictlyPHP\Dolphin\Strategy\DolphinAppStrategy;
use StrictlyPHP\Dolphin\Strategy\DtoMapper;

class App
{
    public function __construct(
        private readonly RequestHandlerInterface $router
    ) {
    }

    /**
     * @param string[] $controllers
     */
    public static function build(
        array $controllers,
    ): self {
        if (empty($controllers)) {
            throw new \InvalidArgumentException('No controllers provided');
        }
        $container = new Container();

        $strategy = new DolphinAppStrategy(
            new DtoMapper(),
            new ResponseFactory()
        );
        $strategy->setContainer($container);
        $router = new Router();
        $router->setStrategy($strategy);

        $classes = [];
        ClassFinder::disablePSR4Vendors();
        foreach ($controllers as $controller) {
            if (class_exists($controller)) {
                $classes[] = $controller;
            } else {
                $classes = array_merge(
                    $classes,
                    ClassFinder::getClassesInNamespace($controller, ClassFinder::RECURSIVE_MODE)
                );
            }
        }

        if (empty($classes)) {
            throw new \InvalidArgumentException('No classes found');
        }

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(Route::class);
            foreach ($attributes as $attribute) {
                /** @var Method $requestMethod */
                $requestMethod = $attribute->getArguments()[0];

                /** @var string $requestPath */
                $requestPath = $attribute->getArguments()[1];

                $router->map($requestMethod->value, $requestPath, $class);
            }
        }

        return new self($router);
    }

    public function getRouter(): RequestHandlerInterface
    {
        return $this->router;
    }

    public function run(array $event, object $context): array
    {
        parse_str($event['http']['headers']['cookie'] ?? '', $cookies);
        $request = new Request(
            $event['http']['method'],
            (new UriFactory())->createUri(
                sprintf(
                    '%s/%s%s',
                    $context->apiHost,
                    $event['http']['path'],
                    ! empty($event['http']['queryString']) ? '?' . $event['http']['queryString'] : ''
                )
            ),
            new Headers($event['http']['headers']),
            $cookies,
            [],
            (new StreamFactory())->createStream($event['http']['body'])
        );

        try {
            $response = $this->router->handle($request);

            return [
                'statusCode' => $response->getStatusCode(),
                'body' => $response->getBody()->getContents(),
                'headers' => $response->getHeaders(),
            ];
        } catch (\Exception $e) {
            return [
                'statusCode' => 500,
                'body' => json_encode([
                    'error' => $e->getMessage(),
                    'trace' => $e->getTrace(),
                ]),
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ];
        }
    }
}
