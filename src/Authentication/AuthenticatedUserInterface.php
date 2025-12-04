<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Authentication;

interface AuthenticatedUserInterface
{
    public function getId(): string;

    /**
     * @return array<int, string>
     */
    public function getRoles(): array;
}
