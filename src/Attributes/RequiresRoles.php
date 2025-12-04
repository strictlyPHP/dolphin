<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class RequiresRoles
{
    /**
     * @param string[] $roles
     */
    public function __construct(
        public array $roles
    ) {
    }
}
