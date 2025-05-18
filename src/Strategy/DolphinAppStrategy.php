<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Strategy;

use League\Route\Http\Exception\BadRequestException;
use League\Route\Route;
use League\Route\Strategy\JsonStrategy;
use Monolog\Logger;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;
use League\Route\Http;

class DolphinAppStrategy extends JsonStrategy
{
    public function __construct(
        private DtoMapper $dtoMapper,
        ResponseFactoryInterface $responseFactory,
        private ?LoggerInterface $logger = null,
        ?int $jsonFlags = 0
    ) {
        parent::__construct($responseFactory, $jsonFlags);
    }

    public function getThrowableHandler(): MiddlewareInterface
    {
        return new class (
            $this->responseFactory->createResponse(),
            $this->logger
        ) implements MiddlewareInterface
        {

            public function __construct(
                protected ResponseInterface $response,
                private ?LoggerInterface $logger = null,
            ) {}

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                try {
                    return $handler->handle($request);
                } catch (Throwable $exception) {
                    $response = $this->response;

                    if ($exception instanceof Http\Exception) {
                        $statusCode = $exception->getStatusCode();
                        $message = $exception->getMessage();
                        $this->logger->warning(
                            $message,
                            [
                                'message'  => $exception->getMessage(),
                                'request'   => (string)$request->getBody(),
                                'trace'    => $exception->getTrace()
                            ]
                        );
                    } else {
                        $statusCode = 500;
                        $message = 'Internal Server Error';
                        $this->logger->critical(
                            $message,
                            [
                                'message'  => $exception->getMessage(),
                                'request'   => (string)$request->getBody(),
                                'trace'    => $exception->getTrace()
                            ]
                        );
                    }

                    $response->getBody()->write(json_encode([
                        'statusCode'   => $statusCode,
                        'reasonPhrase' => $message,
                    ]));

                    $response = $response->withAddedHeader('content-type', 'application/json');
                    return $response->withStatus($statusCode);
                }
            }
        };
    }

    public function invokeRouteCallable(Route $route, ServerRequestInterface $request): ResponseInterface
    {
        $callable = $route->getCallable($this->getContainer());

        if (is_array($callable)) {
            $ref = new ReflectionMethod($callable[0], $callable[1]);
        } elseif (is_string($callable) && strpos($callable, '::') !== false) {
            [$class, $method] = explode('::', $callable);
            $ref = new ReflectionMethod($class, $method);
        } elseif (is_object($callable) && method_exists($callable, '__invoke')) {
            $ref = new ReflectionMethod($callable, '__invoke');
        } else {
            $ref = new ReflectionFunction($callable);
        }

        $parameters = $ref->getParameters();
        $args = [];

        foreach ($parameters as $param) {
            $type = $param->getType();
            if (! $type instanceof ReflectionNamedType) {
                throw new \Exception(sprintf('parameter %s has no type', $param->getName()));
            }

            $typeName = $type->getName();

            if ($typeName === ServerRequestInterface::class) {
                $args[] = $request;
                continue;
            }

            if ($typeName === 'array') {
                $args[] = $route->getVars();
                continue;
            }

            if (class_exists($typeName)) {
                $body = $request->getBody()->getContents();
                $data = json_decode($body, true);

                if (! is_array($data)) {
                    throw new BadRequestException('Invalid JSON body');
                }

                $dto = $this->dtoMapper->map($typeName, $data);
                $args[] = $dto;
            }
        }

        $response = $callable(...$args);

        if ($this->isJsonSerializable($response)) {
            $body = json_encode($response, $this->jsonFlags);
            $response = $this->responseFactory->createResponse();
            $response->getBody()->write($body);
        }

        return $this->decorateResponse($response);
    }
}
