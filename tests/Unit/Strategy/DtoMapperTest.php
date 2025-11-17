<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Dolphin\Request\Method;
use StrictlyPHP\Dolphin\Strategy\DtoMapper;
use StrictlyPHP\Dolphin\Strategy\Exception\DtoMapperException;
use StrictlyPHP\Tests\Dolphin\Fixtures\Request\RequestId;
use StrictlyPHP\Tests\Dolphin\Fixtures\Request\TestArrayRequestDto;
use StrictlyPHP\Tests\Dolphin\Fixtures\Request\TestEnumRequestDto;
use StrictlyPHP\Tests\Dolphin\Fixtures\Request\TestNullableRequestDto;
use StrictlyPHP\Tests\Dolphin\Fixtures\Request\TestRequestDto;
use StrictlyPHP\Tests\Dolphin\Fixtures\Request\TestRequestSameNamespaceDto;
use StrictlyPHP\Tests\Dolphin\Fixtures\Value\EmailAddress;
use StrictlyPHP\Tests\Dolphin\Fixtures\Value\PersonName;

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

    public function testDtoMapperReturnsDtoWithNullableRequest(): void
    {
        $emailAddress = new EmailAddress('test@example.com');
        $dto = $this->dtoMapper->map(
            TestNullableRequestDto::class,
            [
                'email' => 'test@example.com',
            ]
        );

        $this->assertEquals($emailAddress, $dto->email);

        $dto = $this->dtoMapper->map(
            TestNullableRequestDto::class,
            []
        );
        $this->assertNull($dto->email);
    }

    public function testDtoMapperReturnsDtoWithEnumRequest(): void
    {
        $dto = $this->dtoMapper->map(
            TestEnumRequestDto::class,
            [
                'method' => 'POST',
            ]
        );

        $this->assertEquals(Method::POST, $dto->method);
    }

    public function testDtoMapperReturnsDtoWithSameNamespace(): void
    {
        $dto = $this->dtoMapper->map(
            TestRequestSameNamespaceDto::class,
            [
                'requestId' => '1a13aefe-8d58-407c-9ade-b44c897ccc42',
            ]
        );

        $this->assertEquals(new RequestId('1a13aefe-8d58-407c-9ade-b44c897ccc42'), $dto->requestId);
    }

    public function testArrayWithNullableElements(): void
    {
        $dto = $this->dtoMapper->map(
            TestArrayRequestDto::class,
            [
                'data' => [[
                    'foo' => 'bar',
                ]],
                'words' => ['hello', 'world'],
                'users' => [
                    [
                        'givenName' => 'Jane',
                        'familyName' => 'Doe',
                    ],
                ],
                'emails' => ['jane.doe@example.com', null],
            ]
        );

        $this->assertInstanceOf(TestArrayRequestDto::class, $dto);
    }

    public function testArrayWithValues(): void
    {
        $dto = $this->dtoMapper->map(
            TestArrayRequestDto::class,
            [
                'data' => [],
                'words' => [],
                'users' => [],
                'emails' => ['hello@example.com'],
            ]
        );

        $this->assertEquals([new EmailAddress('hello@example.com')], $dto->emails);
    }
}
