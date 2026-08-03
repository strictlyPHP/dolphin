<?php

declare(strict_types=1);
namespace StrictlyPHP\Dolphin\Response;

use Fig\Http\Message\StatusCodeInterface;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Response;
use StrictlyPHP\Dolphin\Json\SafeJsonEncoder;

class JsonResponse extends Response
{
    /**
     * @param \JsonSerializable|array<mixed, mixed> $body
     */
    public function __construct(\JsonSerializable|array $body, ?int $status = StatusCodeInterface::STATUS_OK)
    {
        // Safe flags degrade malformed UTF-8 (e.g. legacy latin1 data) to the
        // Unicode replacement character rather than failing the whole response.
        // SafeJsonEncoder only returns '' for a genuinely un-encodable body, which
        // we surface as a fail-fast exception that the hardened error handler can
        // then render cleanly (never a write(false) TypeError).
        $encoded = SafeJsonEncoder::encode($body);
        if ($encoded === '') {
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
