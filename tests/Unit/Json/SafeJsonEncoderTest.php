<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Json;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Dolphin\Json\SafeJsonEncoder;

class SafeJsonEncoderTest extends TestCase
{
    public function testItEncodesValidData(): void
    {
        $this->assertSame('{"a":1}', SafeJsonEncoder::encode([
            'a' => 1,
        ]));
    }

    public function testItSubstitutesInvalidUtf8(): void
    {
        $encoded = SafeJsonEncoder::encode([
            'invalid' => "\xB1\x31",
        ]);

        $this->assertNotSame('', $encoded);
        $this->assertSame([
            'invalid' => "\u{FFFD}1",
        ], json_decode($encoded, true));
    }

    public function testItReturnsStringForRecursiveStructure(): void
    {
        $data = [
            'self' => null,
        ];
        $data['self'] = &$data;

        $encoded = SafeJsonEncoder::encode($data);

        // JSON_PARTIAL_OUTPUT_ON_ERROR keeps json_encode from returning false on a
        // recursive structure; the helper must therefore always hand back a string.
        $this->assertIsString($encoded);
    }

    public function testItReturnsStringForNanAndInf(): void
    {
        $encoded = SafeJsonEncoder::encode([
            'nan' => NAN,
            'inf' => INF,
        ]);

        $this->assertIsString($encoded);
        $this->assertNotSame('', $encoded);
    }

    public function testItHonoursAdditionalFlags(): void
    {
        $encoded = SafeJsonEncoder::encode([
            'url' => 'a/b',
        ], JSON_UNESCAPED_SLASHES);

        $this->assertSame('{"url":"a/b"}', $encoded);
    }
}
