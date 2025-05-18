<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures;

readonly class TestRequestDto
{
    public function __construct(
        public string $username,
        public string $email,
        public PersonName $name
    ) {
    }
}
