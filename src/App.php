<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin;

use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\UriFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;

class App
{
    public function __construct(
        private RequestHandlerInterface $router
    ) {
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

        $response = $this->router->handle($request);

        return [
            'statusCode' => $response->getStatusCode(),
            'body' => $response->getBody()->getContents(),
            'headers' => $response->getHeaders(),
        ];
    }
}
