<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Attributes;

use StrictlyPHP\Dolphin\Request\Method;

#[\Attribute]
class Route
{
    public function __construct(
        public string $method,
        public string $path
    ) {
        if (! Method::isValid($this->method)) {
            throw new \InvalidArgumentException('Invalid request method');
        }
    }
}
