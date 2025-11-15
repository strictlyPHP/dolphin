<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Dolphin\Strategy\DtoMapper;
use StrictlyPHP\Dolphin\Strategy\Exception\DtoMapperException;
use StrictlyPHP\Tests\Dolphin\Fixtures\EmailAddress;
use StrictlyPHP\Tests\Dolphin\Fixtures\PersonName;
use StrictlyPHP\Tests\Dolphin\Fixtures\TestArrayRequestDto;
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

    public function testDtoMapperReturnsDto(): void
    {
        $dto = $this->dtoMapper->map(
            TestRequestDto::class,
            [
                'username' => 'john.doe',
                'email' => 'john.doe@example.com',
                'name' => [
                    'givenName' => 'John',
                    'familyName' => 'Doe',
                ],
            ]
        );

        $emailAddress = new EmailAddress('john.doe@example.com');
        $name = new PersonName('John', 'Doe');
        $this->assertEquals('john.doe', $dto->username);
        $this->assertEquals($emailAddress, $dto->email);
        $this->assertEquals($name, $dto->name);
    }

    public function testDtoMapperReturnsArrayDto(): void
    {
        $dto = $this->dtoMapper->map(
            TestArrayRequestDto::class,
            [
                'data' => [
                    [
                        'foo' => 'bar',
                    ],
                ],
                'words' => ['john', 'doe'],
                'users' => [
                    [
                        'givenName' => 'John',
                        'familyName' => 'Doe',
                    ],
                ],
                'emails' => [
                    'john.doe@example.com',
                ],
            ]
        );

        $expectedData = [
            [
                'foo' => 'bar',
            ],
        ];
        $this->assertEquals($expectedData, $dto->data);

        $expectedWords = ['john', 'doe'];
        $this->assertEquals($expectedWords, $dto->words);

        $expectedUsers = [new PersonName('John', 'Doe')];
        $this->assertEquals($expectedUsers, $dto->users);

        $emails = [new EmailAddress('john.doe@example.com')];
        $this->assertEquals($emails, $dto->emails);
    }
}
