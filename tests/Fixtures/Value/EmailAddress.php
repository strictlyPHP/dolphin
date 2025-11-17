<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures\Value;

class EmailAddress
{
    public function __construct(
        public string $value
    ) {
    }
}
