<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures;

use StrictlyPHP\Tests\Dolphin\Fixtures\Value\EmailAddress;
use StrictlyPHP\Tests\Dolphin\Fixtures\Value\PersonName;

class TestService
{
    public function run(
        string $username,
        EmailAddress $email,
        PersonName $name
    ): string {
        if ($username === 'internal-server-error') {
            throw new \Exception('Forced internal server error');
        }
        return sprintf('created user %s with email %s and name %s', $username, $email->value, $name->getFullName());
    }
}
