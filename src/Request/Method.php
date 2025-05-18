<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Request;

use MyCLabs\Enum\Enum;

/**
 * @extends Enum<string>
 * @method static self GET()
 * @method static self POST()
 * @method static self PUT()
 * @method static self DELETE()
 * @method static self PATCH()
 * @method static self OPTIONS()
 * @method static self HEAD()
 */
class Method extends Enum
{
    public const GET = 'GET';

    public const POST = 'POST';

    public const PUT = 'PUT';

    public const DELETE = 'DELETE';

    public const PATCH = 'PATCH';

    public const OPTIONS = 'OPTIONS';

    public const HEAD = 'HEAD';
}
