<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures\Request;

use StrictlyPHP\Tests\Dolphin\Fixtures\Enum\Status;
use StrictlyPHP\Tests\Dolphin\Fixtures\Value\EmailAddress;
use StrictlyPHP\Tests\Dolphin\Fixtures\Value\PersonName;

class TestArrayRequestDto
{
    /**
     * @param array<int, string> $words
     * @param array<int, PersonName> $users
     * @param array<EmailAddress> $emails
     * @param array<int, Status> $statuses
     */
    public function __construct(
        public array $data,
        public array $words,
        public array $users,
        public array $emails,
        public array $statuses
    ) {
    }
}
