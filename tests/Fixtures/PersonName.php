<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures;

class PersonName
{
    public function __construct(
        private string $givenName,
        private string $familyName
    ) {
    }

    public function getFullName(): string
    {
        return sprintf('%s %s', $this->givenName, $this->familyName);
    }
}
