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
    public function __construct(\JsonSerializable|array $body, ?int $status = StatusCodeInterface::STATUS_OK)
    {
        $encoded = json_encode($body);
        if ($encoded === false) {
            throw new \RuntimeException('json_encode failed: ' . json_last_error_msg());
        }

        parent::__construct(
            $status,
            new Headers([
                'Content-Type' => 'application/json',
            ]),
            (new StreamFactory())->createStream($encoded)
        );
    }
}
