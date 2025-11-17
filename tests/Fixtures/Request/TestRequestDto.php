<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures\Request;

use StrictlyPHP\Tests\Dolphin\Fixtures\Value\EmailAddress;
use StrictlyPHP\Tests\Dolphin\Fixtures\Value\PersonName;

readonly class TestRequestDto
{
    public function __construct(
        public string $username,
        public EmailAddress $email,
        public PersonName $name
    ) {
    }
}
