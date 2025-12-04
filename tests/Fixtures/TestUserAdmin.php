<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures;

use StrictlyPHP\Dolphin\Authentication\AuthenticatedUserInterface;

class TestUserAdmin implements AuthenticatedUserInterface
{
    public function getId(): string
    {
        return 'test-user';
    }

    public function getRoles(): array
    {
        return [
            'ADMIN',
        ];
    }
}
