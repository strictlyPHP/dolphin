<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Response;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Dolphin\Response\JsonResponse;

class JsonResponseTest extends TestCase
{
    public function testItThrowsExceptionWhenJsonEncodeFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('json_encode failed:');

        new JsonResponse([
            'invalid' => "\xB1\x31",
        ]);
    }
}
