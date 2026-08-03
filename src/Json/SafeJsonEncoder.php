<?php

declare(strict_types=1);

namespace StrictlyPHP\Dolphin\Json;

/**
 * Safe wrapper around json_encode() that is guaranteed never to return false.
 *
 * json_encode() returns false (it does not throw, unless JSON_THROW_ON_ERROR is
 * set) when given malformed UTF-8, recursive structures, depth overflow, or
 * NAN/INF. Passing that false straight to a PSR-7 Stream::write() raises a
 * TypeError that masks the real, already-logged error. This helper degrades bad
 * bytes to the Unicode replacement character and emits partial output on the
 * remaining edge cases, so callers can always hand the result to write().
 */
final class SafeJsonEncoder
{
    public static function encode(mixed $data, int $flags = 0): string
    {
        $json = json_encode($data, $flags | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $json === false ? '' : $json;
    }
}
