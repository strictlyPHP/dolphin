<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures;

class TestRequestDto
{
    public function __construct(
        private string $username,
        private EmailAddress $email,
        private PersonName $name
    ) {
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): EmailAddress
    {
        return $this->email;
    }

    public function getName(): PersonName
    {
        return $this->name;
    }
}
