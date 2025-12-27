<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures\Request;

use StrictlyPHP\Tests\Dolphin\Fixtures\Enum\Foo;

readonly class UnitEnumRequestDto
{
    public function __construct(
        public Foo $foo
    ) {
    }
}
