<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures\Request;

use StrictlyPHP\Tests\Dolphin\Fixtures\Value\EmailAddress;

class TestNullableRequestDto
{
    public function __construct(
        public ?EmailAddress $email
    ) {
    }
}
