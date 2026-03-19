<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures\Request;

use StrictlyPHP\Tests\Dolphin\Fixtures\Enum\Status;
use StrictlyPHP\Tests\Dolphin\Fixtures\Value\EmailAddress;
use StrictlyPHP\Tests\Dolphin\Fixtures\Value\PersonName;

readonly class TestUnionTypeRequestDto
{
    public function __construct(
        public string|int $id,
        public EmailAddress|null $email,
        public Status|string $status,
        public string|int|null $optional,
        public EmailAddress|string $contact,
        public PersonName|array $metadata,
    ) {
    }
}
