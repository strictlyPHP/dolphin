<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Response;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Dolphin\Response\JsonResponse;

class JsonResponseTest extends TestCase
{
    public function testItEncodesValidBody(): void
    {
        $response = new JsonResponse([
            'response' => 'ok',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"response":"ok"}', (string) $response->getBody());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testItDegradesMalformedUtf8InsteadOfThrowing(): void
    {
        $response = new JsonResponse([
            'invalid' => "\xB1\x31",
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $body = (string) $response->getBody();
        // The malformed byte is substituted with the Unicode replacement character
        // and the response is still valid JSON, rather than a hard failure.
        $this->assertIsArray(json_decode($body, true));
        $this->assertSame([
            'invalid' => "\u{FFFD}1",
        ], json_decode($body, true));
    }
}
