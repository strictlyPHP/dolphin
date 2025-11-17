<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures\Request;

readonly class TestRequestSameNamespaceDto
{
    public function __construct(
        public RequestId $requestId
    ) {
    }
}
