<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Strategy;

use League\Route\Http\Exception\BadRequestException;
use League\Route\Route;
use League\Route\Strategy\JsonStrategy;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

class DolphinAppStrategy extends JsonStrategy
{
    public function __construct(
        private DtoMapper $dtoMapper,
        ResponseFactoryInterface $responseFactory,
        ?int $jsonFlags = 0
    ) {
        parent::__construct($responseFactory, $jsonFlags);
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
