<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures;

class TestService
{
    public function run(string $contents): string
    {
        return $contents;
    }
}
