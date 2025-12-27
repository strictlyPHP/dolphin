<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Fixtures\Enum;

enum Status: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
}
