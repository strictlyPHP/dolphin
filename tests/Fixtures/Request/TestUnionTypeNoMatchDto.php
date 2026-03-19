<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures\Request;

readonly class TestUnionTypeNoMatchDto
{
    public function __construct(
        public int|float $value,
    ) {
    }
}
