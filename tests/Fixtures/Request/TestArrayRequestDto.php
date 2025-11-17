<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures\Request;

use StrictlyPHP\Tests\Dolphin\Fixtures\Value\EmailAddress;
use StrictlyPHP\Tests\Dolphin\Fixtures\Value\PersonName;

class TestArrayRequestDto
{
    /**
     * @param array<int, string> $words
     * @param array<int, PersonName> $users
     * @param array<EmailAddress> $emails
     */
    public function __construct(
        public array $data,
        public array $words,
        public array $users,
        public array $emails
    ) {
    }
}
