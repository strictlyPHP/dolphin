<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures\Request;

use StrictlyPHP\Tests\Dolphin\Fixtures\Enum\Status;
use StrictlyPHP\Tests\Dolphin\Fixtures\Value\EmailAddress;
use StrictlyPHP\Tests\Dolphin\Fixtures\Value\PersonName;

class TestKeyedArrayRequestDto
{
    /**
     * @param array<string, string> $labels
     * @param array<string, ?Status> $statuses
     * @param array<string, EmailAddress> $emails
     * @param array<string, PersonName> $people
     */
    public function __construct(
        public array $labels,
        public array $statuses,
        public array $emails,
        public array $people
    ) {
    }
}
