<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Dolphin\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Dolphin\Request\Method;
use StrictlyPHP\Dolphin\Strategy\DtoMapper;
use StrictlyPHP\Dolphin\Strategy\Exception\DtoMapperException;
use StrictlyPHP\Tests\Dolphin\Fixtures\Enum\Foo;
use StrictlyPHP\Tests\Dolphin\Fixtures\Enum\Status;
use StrictlyPHP\Tests\Dolphin\Fixtures\Request\RequestId;
use StrictlyPHP\Tests\Dolphin\Fixtures\Request\TestArrayRequestDto;
use StrictlyPHP\Tests\Dolphin\Fixtures\Request\TestEnumRequestDto;
use StrictlyPHP\Tests\Dolphin\Fixtures\Request\TestNullableRequestDto;
use StrictlyPHP\Tests\Dolphin\Fixtures\Request\TestRequestDto;
use StrictlyPHP\Tests\Dolphin\Fixtures\Request\TestRequestSameNamespaceDto;
use StrictlyPHP\Tests\Dolphin\Fixtures\Request\TestUnionTypeRequestDto;
use StrictlyPHP\Tests\Dolphin\Fixtures\Request\UnitEnumRequestDto;
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
                'statuses' => ['ACTIVE'],
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

        $statuses = [Status::ACTIVE];
        $this->assertEquals($statuses, $dto->statuses);
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

    public function testArrayWithEmptyValues(): void
    {
        $dto = $this->dtoMapper->map(
            TestArrayRequestDto::class,
            [
                'data' => [],
                'words' => [],
                'users' => [],
                'emails' => [],
                'statuses' => [],
            ]
        );

        $this->assertEmpty($dto->data);
        $this->assertEmpty($dto->words);
        $this->assertEmpty($dto->users);
        $this->assertEmpty($dto->emails);
        $this->assertEmpty($dto->statuses);
    }

    public function testArrayWithIncorrectEnumValue(): void
    {
        $this->expectException(DtoMapperException::class);
        $this->expectExceptionMessage(
            sprintf('Could not map value "FOOO" to enum "%s"', Status::class)
        );
        $this->dtoMapper->map(
            TestArrayRequestDto::class,
            [
                'data' => [],
                'words' => [],
                'users' => [],
                'emails' => [],
                'statuses' => ['FOOO'],
            ]
        );
    }

    public function testUnionTypeScalarString(): void
    {
        $dto = $this->dtoMapper->map(
            TestUnionTypeRequestDto::class,
            [
                'id' => 'abc',
                'email' => 'a@b.com',
                'status' => 'ACTIVE',
                'optional' => null,
                'contact' => 'a@b.com',
                'metadata' => [
                    'givenName' => 'John',
                    'familyName' => 'Doe',
                ],
            ]
        );

        $this->assertIsString($dto->id);
        $this->assertSame('abc', $dto->id);
    }

    public function testUnionTypeScalarInt(): void
    {
        $dto = $this->dtoMapper->map(
            TestUnionTypeRequestDto::class,
            [
                'id' => 42,
                'email' => 'a@b.com',
                'status' => 'ACTIVE',
                'optional' => null,
                'contact' => 'a@b.com',
                'metadata' => [
                    'givenName' => 'John',
                    'familyName' => 'Doe',
                ],
            ]
        );

        $this->assertIsInt($dto->id);
        $this->assertSame(42, $dto->id);
    }

    public function testUnionTypeClassOrNullWithValue(): void
    {
        $dto = $this->dtoMapper->map(
            TestUnionTypeRequestDto::class,
            [
                'id' => 'abc',
                'email' => 'a@b.com',
                'status' => 'ACTIVE',
                'optional' => null,
                'contact' => 'a@b.com',
                'metadata' => [
                    'givenName' => 'John',
                    'familyName' => 'Doe',
                ],
            ]
        );

        $this->assertInstanceOf(EmailAddress::class, $dto->email);
        $this->assertSame('a@b.com', $dto->email->value);
    }

    public function testUnionTypeClassOrNullWithNull(): void
    {
        $dto = $this->dtoMapper->map(
            TestUnionTypeRequestDto::class,
            [
                'id' => 'abc',
                'email' => null,
                'status' => 'ACTIVE',
                'optional' => null,
                'contact' => 'a@b.com',
                'metadata' => [
                    'givenName' => 'John',
                    'familyName' => 'Doe',
                ],
            ]
        );

        $this->assertNull($dto->email);
    }

    public function testUnionTypeEnumWinsOverScalar(): void
    {
        $dto = $this->dtoMapper->map(
            TestUnionTypeRequestDto::class,
            [
                'id' => 'abc',
                'email' => 'a@b.com',
                'status' => 'ACTIVE',
                'optional' => null,
                'contact' => 'a@b.com',
                'metadata' => [
                    'givenName' => 'John',
                    'familyName' => 'Doe',
                ],
            ]
        );

        $this->assertInstanceOf(Status::class, $dto->status);
        $this->assertSame(Status::ACTIVE, $dto->status);
    }

    public function testUnionTypeScalarFallbackWhenEnumFails(): void
    {
        $dto = $this->dtoMapper->map(
            TestUnionTypeRequestDto::class,
            [
                'id' => 'abc',
                'email' => 'a@b.com',
                'status' => 'random',
                'optional' => null,
                'contact' => 'a@b.com',
                'metadata' => [
                    'givenName' => 'John',
                    'familyName' => 'Doe',
                ],
            ]
        );

        $this->assertIsString($dto->status);
        $this->assertSame('random', $dto->status);
    }

    public function testUnionTypeTripleNullableNull(): void
    {
        $dto = $this->dtoMapper->map(
            TestUnionTypeRequestDto::class,
            [
                'id' => 'abc',
                'email' => 'a@b.com',
                'status' => 'ACTIVE',
                'optional' => null,
                'contact' => 'a@b.com',
                'metadata' => [
                    'givenName' => 'John',
                    'familyName' => 'Doe',
                ],
            ]
        );

        $this->assertNull($dto->optional);
    }

    public function testUnionTypeNonNullableWithNullThrows(): void
    {
        $this->expectException(DtoMapperException::class);
        $this->expectExceptionMessage("Missing non-nullable parameter 'id'");

        $this->dtoMapper->map(
            TestUnionTypeRequestDto::class,
            [
                'id' => null,
                'email' => 'a@b.com',
                'status' => 'ACTIVE',
                'optional' => null,
                'contact' => 'a@b.com',
                'metadata' => [
                    'givenName' => 'John',
                    'familyName' => 'Doe',
                ],
            ]
        );
    }

    public function testUnionTypeValueObject(): void
    {
        $dto = $this->dtoMapper->map(
            TestUnionTypeRequestDto::class,
            [
                'id' => 'abc',
                'email' => 'a@b.com',
                'status' => 'ACTIVE',
                'optional' => null,
                'contact' => 'test@example.com',
                'metadata' => [
                    'givenName' => 'John',
                    'familyName' => 'Doe',
                ],
            ]
        );

        $this->assertInstanceOf(EmailAddress::class, $dto->contact);
        $this->assertSame('test@example.com', $dto->contact->value);
    }

    public function testUnionTypeNestedDto(): void
    {
        $dto = $this->dtoMapper->map(
            TestUnionTypeRequestDto::class,
            [
                'id' => 'abc',
                'email' => 'a@b.com',
                'status' => 'ACTIVE',
                'optional' => null,
                'contact' => 'a@b.com',
                'metadata' => [
                    'givenName' => 'John',
                    'familyName' => 'Doe',
                ],
            ]
        );

        $this->assertInstanceOf(PersonName::class, $dto->metadata);
        $this->assertSame('John', $dto->metadata->givenName);
        $this->assertSame('Doe', $dto->metadata->familyName);
    }

    public function testUnionTypeArrayFallback(): void
    {
        $dto = $this->dtoMapper->map(
            TestUnionTypeRequestDto::class,
            [
                'id' => 'abc',
                'email' => 'a@b.com',
                'status' => 'ACTIVE',
                'optional' => null,
                'contact' => 'a@b.com',
                'metadata' => [
                    'foo' => 'bar',
                ],
            ]
        );

        $this->assertIsArray($dto->metadata);
        $this->assertSame([
            'foo' => 'bar',
        ], $dto->metadata);
    }

    public function testUnitEnumValue(): void
    {
        $this->expectException(DtoMapperException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Could not map unit enum "%s". Unit enums are not allowed. consider turning it into a backed enum',
                Foo::class
            )
        );
        $this->dtoMapper->map(
            UnitEnumRequestDto::class,
            [
                'foo' => 'BAR',
            ]
        );
    }
}
