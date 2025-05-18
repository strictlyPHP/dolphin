<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures;

class TestService
{
    public function run(
        string $username,
        string $email,
        PersonName $name
    ): string {
        return sprintf('created user %s with email %s and name %s', $username, $email, $name->getFullName());
    }
}
