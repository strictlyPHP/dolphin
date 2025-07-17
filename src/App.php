<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin;

use DI\ContainerBuilder;
use HaydenPierce\ClassFinder\ClassFinder;
use League\Route\Router;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
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
        private readonly RequestHandlerInterface $router,
        private readonly ContainerInterface $container,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * @param string[] $controllers
     */
    public static function build(
        array $controllers,
        array $containerDefinitions = [],
        ?bool $debugMode = false
    ): self {
        if (empty($controllers)) {
            throw new \InvalidArgumentException('No controllers provided');
        }
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->useAttributes(true);
        if (sizeof($containerDefinitions) > 0) {
            $containerBuilder->addDefinitions($containerDefinitions);
        }
        $container = $containerBuilder->build();

        $logger = new Logger('dolphin');
        // Log INFO and above to stdout
        $logger->pushHandler(new StreamHandler('php://stdout', Level::Info));

        // Log WARNING and above to stderr
        $logger->pushHandler(new StreamHandler('php://stderr', Level::Warning));

        $container->set(LoggerInterface::class, new Logger('dolphin_logger'));

        $strategy = new DolphinAppStrategy(
            dtoMapper: new DtoMapper(),
            responseFactory: new ResponseFactory(),
            logger: $logger,
            debugMode: $debugMode,
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
                $arguments = $attribute->getArguments();

                /** @var Method $requestMethod */
                $requestMethod = $arguments[0] ?? $arguments['method'];

                /** @var string $requestPath */
                $requestPath = $arguments[1] ?? $arguments['path'];

                $router->map($requestMethod->value, $requestPath, $class);
            }
        }

        return new self($router, $container, $logger);
    }

    public function getRouter(): RequestHandlerInterface
    {
        return $this->router;
    }

    public function get(string $id): mixed
    {
        return $this->container->get($id);
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
                'body' => (string) $response->getBody(),
                'headers' => $response->getHeaders(),
            ];
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error($e->getMessage(), [
                    'trace' => $e->getTrace(),
                ]);
            }
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
