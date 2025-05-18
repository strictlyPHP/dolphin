<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Attributes;

use StrictlyPHP\Dolphin\Request\Method;

#[\Attribute]
class Route
{
    public function __construct(
        public Method $method,
        public string $path
    ) {
    }
}
