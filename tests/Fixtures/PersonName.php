<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures;

readonly class PersonName
{
    public function __construct(
        public string $givenName,
        public string $familyName
    ) {
    }

    public function getFullName(): string
    {
        return sprintf('%s %s', $this->givenName, $this->familyName);
    }
}
