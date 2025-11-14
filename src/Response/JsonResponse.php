<?php

declare(strict_types=1);
namespace StrictlyPHP\Dolphin\Response;

use Fig\Http\Message\StatusCodeInterface;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Response;

class JsonResponse extends Response
{
    /**
     * @param \JsonSerializable|array<mixed, mixed> $body
     */
    public function __construct(\JsonSerializable|array $body)
    {
        parent::__construct(
            StatusCodeInterface::STATUS_OK,
            new Headers([
                'Content-Type' => 'application/json',
            ]),
            (new StreamFactory())->createStream(json_encode($body))
        );
    }
}
