<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures\Request;

use StrictlyPHP\Dolphin\Request\Method;

readonly class TestEnumRequestDto
{
    public function __construct(
        public Method $method
    ) {
    }
}
