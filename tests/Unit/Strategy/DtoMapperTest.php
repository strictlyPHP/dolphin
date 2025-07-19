<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Dolphin\Strategy\DtoMapper;
use StrictlyPHP\Dolphin\Strategy\Exception\DtoMapperException;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestRequestDto;

class DtoMapperTest extends TestCase
{
    protected DtoMapper $dtoMapper;

    protected function setUp(): void
    {
        $this->dtoMapper = new DtoMapper();
    }

    public function testDtoMapperThrowsDtoMapperException(): void
    {
        $this->expectException(DtoMapperException::class);
        $this->dtoMapper->map(
            TestRequestDto::class,
            [
                'invalid' => 'data',
            ]
        );
    }
}
