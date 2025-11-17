<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures\Request;

/**
 * This needs to be in the same namespace as the RequestDto
 */
readonly class RequestId
{
    public function __construct(
        public string $requestId
    ) {
    }
}
